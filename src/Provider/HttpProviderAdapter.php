<?php

declare(strict_types=1);

namespace AIGateway\Provider;

use AIGateway\Core\GatewayRequest;
use AIGateway\Core\GatewayResponse;
use AIGateway\Core\Usage;
use Generator;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;
use function sprintf;

/**
 * Transparent HTTP adapter: forwards requests unchanged to a single provider.
 *
 * Only rewrites the resolved model name and auth header. Response bodies and
 * streaming bytes pass through raw.
 */
final readonly class HttpProviderAdapter implements ProviderAdapter
{
    private string $providerFormat;

    public function __construct(
        private string $name,
        private HttpClientInterface $httpClient,
        private ?string $baseUrl = null,
        private ?string $apiKey = null,
        private ?string $completionsPath = null,
        string $providerFormat = 'openai',
    ) {
        $this->providerFormat = $providerFormat;
    }

    /** Provider name used in logs. */
    public function getName(): string
    {
        return $this->name;
    }

    /** Forward a non-streaming request. */
    public function chat(GatewayRequest $request, string $requestedModel): GatewayResponse
    {
        return $this->rawChat($request);
    }

    /** Forward a streaming request. */
    public function chatStream(GatewayRequest $request, string $requestedModel): Generator
    {
        yield from $this->rawChatStream($request);
    }

    private function buildRawBodyString(GatewayRequest $request): string
    {
        if (null === $request->rawBody) {
            throw new \RuntimeException('rawBody is required for transparent proxy');
        }

        $data = json_decode($request->rawBody, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new \RuntimeException('JSON object body is required for transparent proxy');
        }

        $data['model'] = $request->model;

        return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function buildRequestOptions(GatewayRequest $request, string $bodyString): array
    {
        $options = [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => $bodyString,
        ];

        if ('anthropic' === $request->requestFormat) {
            $options['headers']['x-api-key'] = $this->apiKey;
            $options['headers']['anthropic-version'] = '2023-06-01';
        } else {
            $options['auth_bearer'] = $this->apiKey;
        }

        return $options;
    }

    private function buildUrl(): string
    {
        $defaultPath = 'anthropic' === $this->providerFormat ? '/v1/messages' : '/chat/completions';

        return ($this->baseUrl ?? '') . ($this->completionsPath ?? $defaultPath);
    }

    private function rawChat(GatewayRequest $request): GatewayResponse
    {
        $bodyString = $this->buildRawBodyString($request);
        $url = $this->buildUrl();
        $maxRetries = 3;

        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            $response = $this->httpClient->request('POST', $url, $this->buildRequestOptions($request, $bodyString));

            $statusCode = $response->getStatusCode();
            $raw = $response->getContent(false);

            if (429 === $statusCode && $attempt < $maxRetries) {
                $retryAfter = (int) ($response->getHeaders(false)['retry-after'][0] ?? 0);
                usleep(($retryAfter > 0 ? $retryAfter : ($attempt + 1)) * 1000000);
                continue;
            }

            if ($statusCode >= 400) {
                // Return provider error as-is with its own status code
                return new GatewayResponse(
                    id: sprintf('%s-%s', $this->name, bin2hex(random_bytes(8))),
                    model: $request->model,
                    provider: $this->name,
                    usage: new Usage(),
                    statusCode: $statusCode,
                    rawBody: $raw,
                );
            }

            break;
        }

        // Parse response for token tracking (raw body is always returned as-is)
        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new GatewayResponse(
                id: sprintf('%s-%s', $this->name, bin2hex(random_bytes(8))),
                model: $request->model,
                provider: $this->name,
                usage: new Usage(),
                statusCode: $statusCode,
                rawBody: $raw,
            );
        }

        // Handle both OpenAI format (choices) and Anthropic format (content)
        if (isset($data['choices'][0])) {
            $usageData = $data['usage'] ?? [];
            $promptTokens = (int) ($usageData['prompt_tokens'] ?? 0);
            $completionTokens = (int) ($usageData['completion_tokens'] ?? 0);
        } elseif (isset($data['content']) && is_array($data['content'])) {
            $usageData = $data['usage'] ?? [];
            $promptTokens = (int) ($usageData['input_tokens'] ?? 0);
            $completionTokens = (int) ($usageData['output_tokens'] ?? 0);
        } else {
            $promptTokens = 0;
            $completionTokens = 0;
        }

        return new GatewayResponse(
            id: $data['id'] ?? sprintf('%s-%s', $this->name, bin2hex(random_bytes(8))),
            model: $data['model'] ?? $request->model,
            provider: $this->name,
            usage: new Usage($promptTokens, $completionTokens, $promptTokens + $completionTokens),
            statusCode: $statusCode,
            rawBody: $raw,
        );
    }

    private function rawChatStream(GatewayRequest $request): Generator
    {
        $bodyString = $this->buildRawBodyString($request);
        $url = $this->buildUrl();
        $maxRetries = 3;
        $response = null;

        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            $response = $this->httpClient->request('POST', $url, $this->buildRequestOptions($request, $bodyString));

            $statusCode = $response->getStatusCode();

            if (429 === $statusCode && $attempt < $maxRetries) {
                $retryAfter = (int) ($response->getHeaders(false)['retry-after'][0] ?? 0);
                usleep(($retryAfter > 0 ? $retryAfter : ($attempt + 1)) * 1000000);
                continue;
            }

            if ($statusCode >= 400) {
                $raw = $response->getContent(false);
                // Yield the raw error body as SSE so the client receives the provider error
                yield "data: {$raw}\n\n";
                return;
            }

            break;
        }

        // Stream raw SSE bytes — transparent passthrough
        foreach ($this->httpClient->stream($response) as $chunk) {
            yield $chunk->getContent();
        }
    }
}
