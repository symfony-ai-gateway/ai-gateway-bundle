<?php

declare(strict_types=1);

namespace AIGateway\Core;

use AIGateway\Auth\ApiKeyContext;
use AIGateway\Auth\ApiKeyAuthenticator;
use AIGateway\Auth\AuthEnforcer;
use AIGateway\Catalog\GatewayCatalog;
use AIGateway\Exception\GatewayException;
use AIGateway\Logging\RequestLogStore;
use AIGateway\Provider\ProviderAdapter;
use AIGateway\Provider\ProviderAdapterFactory;
use AIGateway\Routing\ModelPricing;
use AIGateway\Routing\ModelRegistry;
use AIGateway\Routing\ModelResolution;
use Generator;
use function sprintf;

/**
 * Central orchestrator for the gateway runtime.
 *
 * Responsibilities:
 * - authenticate incoming gateway keys
 * - resolve model aliases and chains
 * - enforce endpoint/provider format compatibility
 * - forward requests to the correct provider adapter
 * - record usage, cost, duration, and blocked requests
 */
final class Gateway implements GatewayInterface
{
    /** @var array<string, ProviderAdapter> */
    private array $providerCache = [];

    /**
     * @param array<string, ProviderAdapter> $providers
     */
    public function __construct(
        private readonly ModelRegistry $modelRegistry,
        private readonly array $providers = [],
        private readonly ?object $rateLimiter = null,
        private readonly ?AuthEnforcer $authEnforcer = null,
        private readonly ?ApiKeyAuthenticator $authenticator = null,
        private readonly ?GatewayCatalog $catalog = null,
        private readonly ?ProviderAdapterFactory $providerAdapterFactory = null,
        private readonly ?RequestLogStore $requestLogStore = null,
    ) {}

    /** Handle a standard non-streaming request lifecycle from auth to logging. */
    public function chat(GatewayRequest $request): GatewayResponse
    {
        $startTime = microtime(true);
        $context = null;

        try {
            $context = $this->resolveContext($request);

            if (null !== $context && null !== $this->authEnforcer) {
                $this->authEnforcer->checkModelAllowed($context, $request->model);
                $this->authEnforcer->checkBudget($context);
                $this->authEnforcer->checkRateLimit($context);
            }

            $this->rateLimiter?->check(['global' => 'global', 'model' => $request->model]);
        } catch (\Throwable $e) {
            $this->requestLogStore?->logBlockedRequest(
                modelAlias: $request->model,
                provider: 'unknown',
                statusCode: 429,
                error: $e->getMessage(),
                keyId: $context?->apiKey->getId(),
                keyName: $context?->apiKey->getName(),
                teamId: $context?->apiKey->getTeamId(),
            );
            throw $e;
        }

        $requestedModel = $request->model;
        $resolution = null;

        // A requested model can either be a chain alias, a statically configured
        // alias, or a runtime-loaded alias stored in the database.
        if (null !== $this->catalog && [] !== $this->catalog->resolveChainSteps($requestedModel)) {
            $response = $this->executeModelChain($request, $requestedModel);
        } elseif ($this->modelRegistry->has($requestedModel)) {
            $resolution = $this->modelRegistry->resolve($requestedModel);
            $response = $this->executeSingle($request, $resolution);
        } elseif (null !== $this->resolveStoredModel($requestedModel)) {
            $resolution = $this->resolveStoredModel($requestedModel);
            $response = $this->executeSingle($request, $resolution);
        } else {
            $available = null !== $this->catalog
                ? array_map(static fn(array $m): string => $m['alias'], $this->catalog->listModels())
                : [];
            throw GatewayException::modelNotFound($requestedModel, $available);
        }

        $durationMs = (microtime(true) - $startTime) * 1000;

        if (null === $resolution) {
            $cost = $response->costUsd;
        } else {
            $cost = $response->costUsd > 0.0
                ? $response->costUsd
                : $resolution->pricing->calculateCost(
                    $response->usage->promptTokens,
                    $response->usage->completionTokens,
                );
        }

        $finalResponse = new GatewayResponse(
            id: $response->id,
            model: $response->model,
            provider: $response->provider,
            usage: $response->usage,
            statusCode: $response->statusCode,
            costUsd: $cost,
            rawBody: $response->rawBody,
        );

        $this->rateLimiter?->increment(['global' => 'global', 'model' => $request->model]);

        if (null !== $context && null !== $this->authEnforcer) {
            $this->authEnforcer->incrementRateLimit($context);
            $this->authEnforcer->recordUsage(
                $context,
                $finalResponse->usage->promptTokens + $finalResponse->usage->completionTokens,
                $cost,
            );
        }

        $this->requestLogStore?->logResponse(
            response: $finalResponse,
            modelAlias: $requestedModel,
            durationMs: $durationMs,
            keyId: $context?->apiKey->getId(),
            keyName: $context?->apiKey->getName(),
            teamId: $context?->apiKey->getTeamId(),
            queryModel: $response->queryModel ?? $resolution?->model,
            resolvedModel: $response->model,
            pickAlias: $response->modelAlias ?? $requestedModel,
        );

        return $finalResponse;
    }

    /** Handle a streaming request lifecycle and log usage after the stream ends. */
    public function chatStream(GatewayRequest $request): Generator
    {
        $startTime = microtime(true);
        $context = $this->resolveContext($request);

        if (null !== $context && null !== $this->authEnforcer) {
            $this->authEnforcer->checkModelAllowed($context, $request->model);
            $this->authEnforcer->checkBudget($context);
            $this->authEnforcer->checkRateLimit($context);
        }

        $streamRequest = new GatewayRequest(
            model: $request->model,
            key: $request->key,
            requestFormat: $request->requestFormat,
            stream: true,
            rawBody: $request->rawBody,
        );

        $chainSteps = null !== $this->catalog ? $this->catalog->resolveChainSteps($streamRequest->model) : [];
        if ([] !== $chainSteps) {
            yield from $this->streamFromChain($streamRequest, $chainSteps, $context, $startTime);
            return;
        }

        if (!$this->modelRegistry->has($streamRequest->model) && null === $this->resolveStoredModel($streamRequest->model)) {
            $available = null !== $this->catalog
                ? array_map(static fn(array $m): string => $m['alias'], $this->catalog->listModels())
                : [];
            throw GatewayException::modelNotFound($streamRequest->model, $available);
        }

        $resolution = $this->modelRegistry->has($streamRequest->model)
            ? $this->modelRegistry->resolve($streamRequest->model)
            : $this->resolveStoredModel($streamRequest->model)
                ?? throw GatewayException::modelNotFound($streamRequest->model, []);
        if (null !== $context && null !== $this->authEnforcer) {
            $this->authEnforcer->incrementRateLimit($context);
        }

        yield from $this->streamSingleAndTrack($streamRequest, $request->model, $resolution, $context, $startTime);
    }

    /** Resolve and validate the incoming gateway key. */
    private function resolveContext(GatewayRequest $request): ApiKeyContext
    {
        if (null === $this->authenticator) {
            throw GatewayException::authenticationFailed('API key authentication is not configured.');
        }
        return $this->authenticator->authenticate($request->key);
    }

    /** Execute a single resolved model against its provider. */
    private function executeSingle(GatewayRequest $request, ModelResolution $resolution): GatewayResponse
    {
        $this->assertRequestFormatMatchesResolution($request, $resolution);

        $adapter = $this->getProvider($resolution->provider);

        return $adapter->chat($this->withModel($request, $resolution->model), $request->model);
    }

    /**
     * Execute a chain in non-streaming mode.
     *
     * Chain steps are filtered by endpoint format first, then evaluated by
     * priority and weight inside the remaining subset.
     */
    private function executeModelChain(GatewayRequest $request, string $chainAlias): GatewayResponse
    {
        $steps = $this->catalog?->resolveChainSteps($chainAlias) ?? [];
        if ([] === $steps) {
            throw GatewayException::modelNotFound($chainAlias, []);
        }

        $steps = $this->filterChainStepsByRequestFormat($steps, $request->requestFormat);
        if ([] === $steps) {
            throw GatewayException::invalidRequest(sprintf(
                'Chain "%s" has no models for "%s" endpoint.',
                $chainAlias,
                $request->requestFormat,
            ));
        }

        $lastException = null;

        /** @var array<int, list<array{id:int, model_alias:string, priority:int, weight:int, resolution:ModelResolution}>> $tiers */
        $tiers = [];
        foreach ($steps as $step) {
            $priority = (int) $step['priority'];
            $tiers[$priority] ??= [];
            $tiers[$priority][] = $step;
        }
        ksort($tiers);

        foreach ($tiers as $tierSteps) {
            $remaining = $tierSteps;

            while ([] !== $remaining) {
                $pickedIndex = $this->pickWeightedStepIndex($remaining);
                $picked = $remaining[$pickedIndex];
                $resolution = $picked['resolution'];

                try {
                    $response = $this->executeSingle($request, $resolution);
                    $chainCost = $resolution->pricing->calculateCost(
                        $response->usage->promptTokens,
                        $response->usage->completionTokens,
                    );

                    return new GatewayResponse(
                        id: $response->id,
                        model: $response->model,
                        provider: $response->provider,
                        usage: $response->usage,
                        statusCode: $response->statusCode,
                        costUsd: $chainCost,
                        modelAlias: $resolution->alias,
                        queryModel: $resolution->model,
                        rawBody: $response->rawBody,
                    );
                } catch (\Throwable $e) {
                    $lastException = $e;
                    unset($remaining[$pickedIndex]);
                    $remaining = array_values($remaining);
                    continue;
                }
            }
        }

        throw $lastException ?? GatewayException::providerError('chain', 503, 'All chain models failed.');
    }

    /**
     * Execute a chain in streaming mode using the same format filtering rules as
     * the non-streaming path.
     */
    private function streamFromChain(GatewayRequest $request, array $steps, ?ApiKeyContext $context, float $startTime): Generator
    {
        $steps = $this->filterChainStepsByRequestFormat($steps, $request->requestFormat);
        if ([] === $steps) {
            throw GatewayException::invalidRequest(sprintf(
                'Chain "%s" has no models for "%s" endpoint.',
                $request->model,
                $request->requestFormat,
            ));
        }

        /** @var array<int, list<array{id:int, model_alias:string, priority:int, weight:int, resolution:ModelResolution}>> $tiers */
        $tiers = [];
        foreach ($steps as $step) {
            $priority = (int) $step['priority'];
            $tiers[$priority] ??= [];
            $tiers[$priority][] = $step;
        }
        ksort($tiers);

        $lastException = null;
        foreach ($tiers as $tierSteps) {
            $pickedIndex = $this->pickWeightedStepIndex($tierSteps);
            $picked = $tierSteps[$pickedIndex];
            $resolution = $picked['resolution'];

            if (null !== $context && null !== $this->authEnforcer) {
                $this->authEnforcer->incrementRateLimit($context);
            }

            yield from $this->streamSingleAndTrack($request, $request->model, $resolution, $context, $startTime);
            return;
        }

        throw $lastException ?? GatewayException::providerError('chain', 503, 'All chain models failed for streaming.');
    }

    /** Return a provider adapter, either prewired or loaded from the catalog. */
    private function getProvider(string $providerName): ProviderAdapter
    {
        if (isset($this->providers[$providerName])) {
            return $this->providers[$providerName];
        }
        if (isset($this->providerCache[$providerName])) {
            return $this->providerCache[$providerName];
        }
        $adapter = $this->createStoredProviderAdapter($providerName);
        if (null !== $adapter) {
            return $adapter;
        }
        throw GatewayException::providerNotFound($providerName);
    }

    /** Build a runtime model resolution from the catalog-backed database. */
    private function resolveStoredModel(string $modelAlias): ?ModelResolution
    {
        if (null === $this->catalog) {
            return null;
        }
        $model = $this->catalog->getModel($modelAlias);
        if (null === $model) {
            return null;
        }
        $this->createStoredProviderAdapter($model['provider_name']);

        return new ModelResolution(
            alias: $model['alias'],
            provider: $model['provider_name'],
            providerFormat: $model['format'] ?? 'openai',
            model: $model['model'],
            pricing: new ModelPricing(
                inputPerMillion: $model['pricing_input'],
                outputPerMillion: $model['pricing_output'],
            ),
        );
    }

    /** Lazily build and cache an adapter for a provider defined in the catalog. */
    private function createStoredProviderAdapter(string $providerName): ?ProviderAdapter
    {
        if ('' === $providerName || null === $this->catalog || null === $this->providerAdapterFactory) {
            return null;
        }
        if (isset($this->providerCache[$providerName])) {
            return $this->providerCache[$providerName];
        }
        $provider = $this->catalog->getProvider($providerName);
        if (null === $provider) {
            return null;
        }
        $adapter = $this->providerAdapterFactory->createAdapter($providerName, [
            'format' => $provider['format'],
            'api_key' => $provider['api_key'],
            'base_url' => $provider['base_url'],
            'completions_path' => $provider['completions_path'],
        ]);
        $this->providerCache[$providerName] = $adapter;
        return $adapter;
    }

    /**
     * Filter chain steps so only models matching the request endpoint format
     * remain available for routing.
     */
    private function filterChainStepsByRequestFormat(array $steps, string $requestFormat): array
    {
        $filtered = [];

        foreach ($steps as $step) {
            $modelAlias = (string) ($step['model_alias'] ?? '');
            if ('' === $modelAlias) {
                continue;
            }

            $resolution = $this->modelRegistry->has($modelAlias)
                ? $this->modelRegistry->resolve($modelAlias)
                : $this->resolveStoredModel($modelAlias);

            if (null === $resolution || $resolution->providerFormat !== $requestFormat) {
                continue;
            }

            $step['resolution'] = $resolution;
            $filtered[] = $step;
        }

        return $filtered;
    }

    /** Pick one step within a priority tier using configured weights. */
    private function pickWeightedStepIndex(array $steps): int
    {
        $total = 0;
        foreach ($steps as $step) {
            $total += max(0, (int) ($step['weight'] ?? 0));
        }
        if ($total <= 0) {
            return 0;
        }
        $roll = random_int(1, $total);
        $cursor = 0;
        foreach ($steps as $index => $step) {
            $cursor += max(0, (int) ($step['weight'] ?? 0));
            if ($roll <= $cursor) {
                return (int) $index;
            }
        }
        return 0;
    }

    /**
     * Proxy a streaming call and derive final usage metadata from raw SSE chunks
     * so the request can still be logged once the stream completes.
     */
    private function streamSingleAndTrack(
        GatewayRequest $request,
        string $requestedModelAlias,
        ModelResolution $resolution,
        ?ApiKeyContext $context,
        float $startTime,
    ): Generator {
        $adapter = $this->getProvider($resolution->provider);
        $this->assertRequestFormatMatchesResolution($request, $resolution);

        $generator = $adapter->chatStream($this->withModel($request, $resolution->model), $requestedModelAlias);

        $finalUsage = null;
        $finalProvider = $resolution->provider;
        $finalModel = $resolution->model;
        $finalId = null;
        $sseBuffer = '';

        foreach ($generator as $chunk) {
            if (is_string($chunk)) {
                $sseBuffer .= $chunk;

                // Try to extract the last complete data: line(s) from the buffer
                while (preg_match('/^data: (.+)\n\n/m', $sseBuffer, $m)) {
                    $payload = trim($m[1]);
                    if ('[DONE]' === $payload || '' === $payload) {
                        $sseBuffer = '';
                        continue;
                    }
                    try {
                        $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
                        if (isset($decoded['id'])) {
                            $finalId = (string) $decoded['id'];
                        }
                        if (isset($decoded['model'])) {
                            $finalModel = (string) $decoded['model'];
                        }
                        if (isset($decoded['provider'])) {
                            $finalProvider = (string) $decoded['provider'];
                        }
                        if (isset($decoded['usage']) && is_array($decoded['usage'])) {
                            $finalUsage = new Usage(
                                (int) ($decoded['usage']['prompt_tokens'] ?? 0),
                                (int) ($decoded['usage']['completion_tokens'] ?? 0),
                                (int) ($decoded['usage']['total_tokens'] ?? 0),
                            );
                        }
                    } catch (\Throwable) {
                        // Skip malformed JSON
                    }
                    $sseBuffer = substr($sseBuffer, strlen($m[0]));
                }

                // Keep buffer trimmed to avoid unbounded growth
                // (guard against SSE lines that never terminate)
                if (strlen($sseBuffer) > 65536) {
                    $sseBuffer = substr($sseBuffer, -65536);
                }
            }

            yield $chunk;
        }

        $usage = $finalUsage instanceof Usage ? $finalUsage : new Usage();
        $cost = $resolution->pricing->calculateCost($usage->promptTokens, $usage->completionTokens);
        $durationMs = (microtime(true) - $startTime) * 1000;

        $response = new GatewayResponse(
            id: $finalId ?? sprintf('%s-%s', $finalProvider, bin2hex(random_bytes(8))),
            model: $finalModel,
            provider: $finalProvider,
            usage: $usage,
            statusCode: 200,
            costUsd: $cost,
        );

        if (null !== $context && null !== $this->authEnforcer) {
            $this->authEnforcer->recordUsage(
                $context,
                $usage->promptTokens + $usage->completionTokens,
                $cost,
            );
        }

        $this->requestLogStore?->logResponse(
            response: $response,
            modelAlias: $requestedModelAlias,
            durationMs: $durationMs,
            keyId: $context?->apiKey->getId(),
            keyName: $context?->apiKey->getName(),
            teamId: $context?->apiKey->getTeamId(),
            queryModel: $resolution->model,
            resolvedModel: $finalModel,
            pickAlias: $resolution->alias ?? $requestedModelAlias,
        );
    }

    /** Return a cloned request with the resolved provider model name. */
    private function withModel(GatewayRequest $request, string $model): GatewayRequest
    {
        return new GatewayRequest(
            model: $model,
            key: $request->key,
            requestFormat: $request->requestFormat,
            stream: $request->stream,
            rawBody: $request->rawBody,
        );
    }

    /** Prevent endpoint/provider format mismatches. */
    private function assertRequestFormatMatchesResolution(GatewayRequest $request, ModelResolution $resolution): void
    {
        if ($request->requestFormat !== $resolution->providerFormat) {
            throw GatewayException::invalidRequest(sprintf(
                'Model "%s" uses provider format "%s" and cannot be called through "%s" endpoint.',
                $request->model,
                $resolution->providerFormat,
                $request->requestFormat,
            ));
        }
    }
}
