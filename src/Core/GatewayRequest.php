<?php

declare(strict_types=1);

namespace AIGateway\Core;

/**
 * Minimal request context carried through the gateway.
 *
 * The gateway forwards the original payload as raw JSON and only needs a few
 * routing fields around it: requested model alias, incoming API key, endpoint
 * format, streaming flag, and the raw body to proxy.
 */
final class GatewayRequest
{
    /**
     * @param string $model Requested model alias exposed by the gateway.
     * @param string $key Incoming gateway API key extracted from request headers.
     * @param string $requestFormat Request family selected by the HTTP endpoint (`openai` or `anthropic`).
     * @param bool $stream Whether the caller expects an SSE stream.
     * @param string|null $rawBody Original JSON request body forwarded to the provider.
     */
    public function __construct(
        public readonly string $model,
        public readonly string $key,
        public readonly string $requestFormat = 'openai',
        public readonly bool $stream = false,
        public readonly ?string $rawBody = null,
    ) {}
}
