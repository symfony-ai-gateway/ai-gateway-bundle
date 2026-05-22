<?php

declare(strict_types=1);

namespace AIGateway\Tests\Unit;

use AIGateway\Core\GatewayRequest;
use PHPUnit\Framework\TestCase;

final class GatewayRequestTest extends TestCase
{
    public function test_minimal_request(): void
    {
        $req = new GatewayRequest(model: 'deepseek', key: 'aigw_test');
        self::assertSame('deepseek', $req->model);
        self::assertSame('aigw_test', $req->key);
        self::assertSame('openai', $req->requestFormat);
        self::assertFalse($req->stream);
        self::assertNull($req->rawBody);
    }

    public function test_anthropic_format(): void
    {
        $req = new GatewayRequest(model: 'claude', key: 'aigw_test', requestFormat: 'anthropic');
        self::assertSame('anthropic', $req->requestFormat);
    }

    public function test_streaming(): void
    {
        $req = new GatewayRequest(model: 'deepseek', key: 'aigw_test', stream: true);
        self::assertTrue($req->stream);
    }

    public function test_raw_body(): void
    {
        $body = '{"model":"deepseek","messages":[{"role":"user","content":"hi"}]}';
        $req = new GatewayRequest(model: 'deepseek', key: 'aigw_test', rawBody: $body);
        self::assertSame($body, $req->rawBody);
    }
}
