<?php

declare(strict_types=1);

namespace AIGateway\Tests\Unit;

use AIGateway\Core\Gateway;
use AIGateway\Exception\GatewayException;
use PHPUnit\Framework\TestCase;

final class GatewayExceptionTest extends TestCase
{
    public function test_authentication_failed(): void
    {
        $e = GatewayException::authenticationFailed('Bad key.');
        self::assertSame(401, $e->getCode());
    }

    public function test_rate_limit_exceeded(): void
    {
        $e = GatewayException::rateLimitExceeded('Too fast.');
        self::assertSame(429, $e->getCode());
    }

    public function test_budget_exceeded(): void
    {
        $e = GatewayException::budgetExceeded('key-1', 'daily', 10.0, 10.5);
        self::assertSame(429, $e->getCode());
    }

    public function test_model_not_found(): void
    {
        $e = GatewayException::modelNotFound('unknown', ['deepseek']);
        self::assertSame(404, $e->getCode());
    }

    public function test_invalid_request(): void
    {
        $e = GatewayException::invalidRequest('Bad input.');
        self::assertSame(400, $e->getCode());
    }

    public function test_provider_error(): void
    {
        $e = GatewayException::providerError('opencode', 500, 'Internal error');
        self::assertSame(500, $e->getCode());
    }
}
