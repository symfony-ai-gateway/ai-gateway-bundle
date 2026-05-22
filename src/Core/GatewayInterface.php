<?php

declare(strict_types=1);

namespace AIGateway\Core;

/**
 * Public contract implemented by the gateway orchestrator.
 */
interface GatewayInterface
{
    /**
     * Execute a non-streaming gateway request.
     */
    public function chat(GatewayRequest $request): GatewayResponse;

    /**
     * Execute a streaming gateway request and yield raw chunks.
     */
    public function chatStream(GatewayRequest $request): \Generator;
}
