<?php

declare(strict_types=1);

namespace AIGateway\Tests\Unit;

use AIGateway\Core\GatewayResponse;
use AIGateway\Core\Usage;
use PHPUnit\Framework\TestCase;

final class GatewayResponseTest extends TestCase
{
    public function test_minimal_response(): void
    {
        $usage = new Usage(promptTokens: 10, completionTokens: 5);
        $res = new GatewayResponse(
            id: 'chatcmpl-abc',
            model: 'deepseek-v4-flash',
            provider: 'opencode',
            usage: $usage,
        );

        self::assertSame('chatcmpl-abc', $res->id);
        self::assertSame('deepseek-v4-flash', $res->model);
        self::assertSame('opencode', $res->provider);
        self::assertSame(200, $res->statusCode);
        self::assertSame(0.0, $res->costUsd);
        self::assertNull($res->rawBody);
        self::assertSame(10, $res->usage->promptTokens);
        self::assertSame(5, $res->usage->completionTokens);
    }

    public function test_with_cost_and_raw_body(): void
    {
        $usage = new Usage();
        $res = new GatewayResponse(
            id: 'msg-1', model: 'claude', provider: 'anthropic',
            usage: $usage, statusCode: 429, costUsd: 0.05,
            rawBody: '{"error":"rate_limit"}',
        );

        self::assertSame(429, $res->statusCode);
        self::assertSame(0.05, $res->costUsd);
        self::assertSame('{"error":"rate_limit"}', $res->rawBody);
    }
}
