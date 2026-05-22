<?php

declare(strict_types=1);

namespace AIGateway\Core;

/**
 * Minimal response context returned by the gateway core.
 *
 * The raw provider body is returned untouched to the client. The extra fields
 * only exist so the gateway can log usage, cost, provider name, and status.
 */
final class GatewayResponse
{
    /**
     * @param string $id Provider response identifier used in logs.
     * @param string $model Resolved provider model name that actually answered.
     * @param string $provider Provider name used for the outgoing call.
     * @param object $usage Token usage object extracted from the provider response.
     * @param int $statusCode HTTP status returned to the client.
     * @param float $costUsd Computed request cost used for budgets and analytics.
     * @param string|null $modelAlias Original gateway model alias (overrides the default).
     * @param string|null $queryModel Resolved model name sent to the provider.
     * @param string|null $rawBody Raw JSON response body sent back to the caller.
     */
    public function __construct(
        public readonly string $id,
        public readonly string $model,
        public readonly string $provider,
        public readonly Usage $usage,
        public readonly int $statusCode = 200,
        public readonly float $costUsd = 0.0,
        public readonly ?string $modelAlias = null,
        public readonly ?string $queryModel = null,
        public readonly ?string $rawBody = null,
    ) {}
}
