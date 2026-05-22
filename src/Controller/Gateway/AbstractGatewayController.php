<?php

declare(strict_types=1);

namespace AIGateway\Controller\Gateway;

use AIGateway\Core\GatewayInterface;
use AIGateway\Core\GatewayRequest;
use AIGateway\Catalog\GatewayCatalog;
use AIGateway\Exception\GatewayException;
use AIGateway\Logging\RequestLogStore;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use const JSON_THROW_ON_ERROR;

/**
 * Base controller for exposing the gateway HTTP API.
 *
 * Subclasses define the route and auth extraction; this class provides the
 * shared proxy logic (nonStreamProxy, streamProxy) and the introspection
 * endpoints.
 */
abstract class AbstractGatewayController
{
    public function __construct(
        protected readonly GatewayInterface $gateway,
        protected readonly ?GatewayCatalog $catalog = null,
        protected readonly ?RequestLogStore $requestLogStore = null,
    ) {}

    protected function handleRequest(Request $request, string $token, string $requestFormat): Response|StreamedResponse
    {
        $rawBody = $request->getContent();
        try {
            $body = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw GatewayException::invalidRequest('Invalid JSON request body.');
        }

        if (!is_array($body)) {
            throw GatewayException::invalidRequest('JSON object request body expected.');
        }

        $gatewayRequest = new GatewayRequest(
            model: $body['model'] ?? '',
            key: $token,
            requestFormat: $requestFormat,
            stream: (bool) ($body['stream'] ?? false),
            rawBody: $rawBody,
        );

        return $gatewayRequest->stream
            ? $this->streamProxy($gatewayRequest)
            : $this->nonStreamProxy($gatewayRequest);
    }

    public function models(): JsonResponse
    {
        $dbModels = $this->catalog?->listModels() ?? [];
        $data = [];
        foreach ($dbModels as $model) {
            $data[] = ['id' => $model['alias'], 'object' => 'model', 'owned_by' => 'aigateway'];
        }

        return new JsonResponse(['object' => 'list', 'data' => $data]);
    }

    public function health(): JsonResponse
    {
        return new JsonResponse([
            'status' => 'ok',
            'providers_configured' => count($this->catalog?->listProviders() ?? []) > 0,
            'models_available' => count($this->catalog?->listModels() ?? []),
        ]);
    }

    protected function nonStreamProxy(GatewayRequest $request): Response
    {
        $response = $this->gateway->chat($request);

        return new Response($response->rawBody ?? '{}', $response->statusCode, [
            'Content-Type' => 'application/json',
        ]);
    }

    protected function streamProxy(GatewayRequest $request): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($request): void {
            set_time_limit(0);
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no');

            if (ob_get_level() > 0) ob_end_clean();

            try {
                foreach ($this->gateway->chatStream($request) as $chunk) {
                    echo $chunk;
                    if (ob_get_level() > 0) ob_flush();
                    flush();
                }
            } catch (\Throwable $e) {
                $error = json_encode(['error' => ['type' => 'gateway_error', 'message' => $e->getMessage()]], JSON_THROW_ON_ERROR);
                echo "data: {$error}\n\n";
                if (ob_get_level() > 0) ob_flush();
                flush();
            }
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }
}
