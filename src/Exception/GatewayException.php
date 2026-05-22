<?php

declare(strict_types=1);

namespace AIGateway\Exception;

/** Typed gateway exception used for error responses. */
final class GatewayException extends \RuntimeException
{
    public static function modelNotFound(string $model, array $available = []): self
    {
        $list = [] !== $available ? ' Available: ' . implode(', ', $available) : '';
        return new self(sprintf('Model "%s" not found.%s', $model, $list), 404);
    }

    public static function providerNotFound(string $provider): self
    {
        return new self(sprintf('Provider "%s" not found.', $provider), 404);
    }

    public static function providerError(string $provider, int $status, string $message): self
    {
        return new self(sprintf('Provider "%s" error (HTTP %d): %s', $provider, $status, $message), $status);
    }

    public static function authenticationFailed(string $message): self
    {
        return new self($message, 401);
    }

    public static function rateLimitExceeded(string $message): self
    {
        return new self($message, 429);
    }

    public static function budgetExceeded(string $keyId, string $period, float $budget, float $usage): self
    {
        return new self(sprintf('Budget exceeded for key %s (%s): $%.4f / $%.4f', $keyId, $period, $usage, $budget), 429);
    }

    public static function invalidRequest(string $message): self
    {
        return new self($message, 400);
    }

    public static function allProvidersFailed(string $model): self
    {
        return new self(sprintf('All providers failed for model "%s".', $model), 503);
    }
}
