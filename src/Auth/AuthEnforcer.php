<?php

declare(strict_types=1);

namespace AIGateway\Auth;

use AIGateway\Auth\Store\KeyStoreInterface;
use AIGateway\Auth\Store\SlidingWindowKeyRateLimiter;
use AIGateway\Exception\GatewayException;

/**
 * Enforces budget limits, rate limits, and model access for each request.
 *
 * The enforcer is called by the gateway after authentication. Each check
 * raises a GatewayException that translates into an HTTP error response.
 */
final class AuthEnforcer
{
    public function __construct(
        /** Key store for reading daily/monthly usage totals. */
        private readonly KeyStoreInterface $keyStore,
        /** Sliding-window rate limiter for per-key throttling. */
        private readonly SlidingWindowKeyRateLimiter $rateLimiter,
    ) {}

    /**
     * Reject the request if the model alias is not in the key's allowed list
     * or is in the blocked list.
     */
    public function checkModelAllowed(ApiKeyContext $context, string $model): void
    {
        if (!$context->resolvedRules->isModelAllowed($model)) {
            throw GatewayException::invalidRequest(sprintf('Model "%s" is not allowed for key "%s".', $model, $context->apiKey->getName()));
        }
    }

    /**
     * Reject the request if the key has exceeded its daily or monthly budget.
     */
    public function checkBudget(ApiKeyContext $context): void
    {
        $today = date('Y-m-d');

        if (null !== $context->resolvedRules->budgetPerDay) {
            $dailyUsage = $this->keyStore->getKeyUsage($context->apiKey->getId() ?? 0, $today, $today);
            if ($dailyUsage->costUsd >= $context->resolvedRules->budgetPerDay) {
                throw GatewayException::budgetExceeded((string) ($context->apiKey->getId() ?? 0), 'daily', $context->resolvedRules->budgetPerDay, $dailyUsage->costUsd);
            }
        }

        if (null !== $context->resolvedRules->budgetPerMonth) {
            $monthStart = date('Y-m-01');
            $monthEnd = date('Y-m-t');
            $monthlyUsage = $this->keyStore->getKeyUsage($context->apiKey->getId() ?? 0, $monthStart, $monthEnd);
            if ($monthlyUsage->costUsd >= $context->resolvedRules->budgetPerMonth) {
                throw GatewayException::budgetExceeded((string) ($context->apiKey->getId() ?? 0), 'monthly', $context->resolvedRules->budgetPerMonth, $monthlyUsage->costUsd);
            }
        }
    }

    /** Reject the request if the key has exceeded its request rate limits. */
    public function checkRateLimit(ApiKeyContext $context): void
    {
        if (null !== $context->resolvedRules->rateLimitPerDay) {
            $today = date('Y-m-d');
            $dailyUsage = $this->keyStore->getKeyUsage($context->apiKey->getId() ?? 0, $today, $today);
            if ($dailyUsage->requests >= $context->resolvedRules->rateLimitPerDay) {
                throw GatewayException::rateLimitExceeded(sprintf('Daily rate limit exceeded for key "%s"', $context->apiKey->getName()));
            }
        }

        if (null !== $context->resolvedRules->rateLimitPerMinute) {
            $result = $this->rateLimiter->isAllowed((string) ($context->apiKey->getId() ?? 0), $context->resolvedRules->rateLimitPerMinute);
            if (!$result->allowed) {
                throw GatewayException::rateLimitExceeded(sprintf('Rate limit exceeded for key "%s"', $context->apiKey->getName()));
            }
        }
    }

    /**
     * Record one request against the key's sliding-window rate counter.
     * Called after the request has passed all checks and is being processed.
     */
    public function incrementRateLimit(ApiKeyContext $context): void
    {
        if (null !== $context->resolvedRules->rateLimitPerMinute) {
            $this->rateLimiter->increment((string) ($context->apiKey->getId() ?? 0));
        }
    }

    /**
     * Persist token/cost usage for this key.
     */
    public function recordUsage(ApiKeyContext $context, int $tokens, float $costUsd): void
    {
        $this->keyStore->incrementKeyUsage($context->apiKey->getId() ?? 0, date('Y-m-d'), $tokens, $costUsd);
    }
}
