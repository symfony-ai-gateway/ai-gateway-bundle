<?php

declare(strict_types=1);

namespace AIGateway\Provider;

use AIGateway\Core\GatewayRequest;
use AIGateway\Core\GatewayResponse;

/**
 * Contract implemented by provider HTTP adapters.
 */
interface ProviderAdapter
{
/** Provider name used in logs. */
    public function getName(): string;

/** Execute one non-streaming request. */
    public function chat(GatewayRequest $request, string $modelAlias): GatewayResponse;

/** Execute one streaming request. */
    public function chatStream(GatewayRequest $request, string $modelAlias): \Generator;
}
