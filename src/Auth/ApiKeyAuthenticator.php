<?php

declare(strict_types=1);

namespace AIGateway\Auth;

use AIGateway\Entity\ApiKey;
use AIGateway\Auth\Store\KeyStoreInterface;
use AIGateway\Exception\GatewayException;

/**
 * Validates incoming gateway keys and produces an authenticated context.
 *
 * The authenticator hashes every incoming token with SHA-256 and looks it up
 * in the key store. If found and enabled, it merges any key-level overrides
 * with the team rules to produce the resolved rate/budget limits used later
 * by the AuthEnforcer.
 */
final class ApiKeyAuthenticator
{
    public function __construct(
        /** Key store used to look up API key hashes. */
        private readonly KeyStoreInterface $keyStore,
    ) {}

    /**
     * Authenticate a raw gateway key (the aigw_xxx value).
     *
     * @throws GatewayException if the key is unknown, disabled, or expired.
     */
    public function authenticate(string $token): ApiKeyContext
    {
        $keyHash = hash('sha256', $token);

        $apiKey = $this->keyStore->findKeyByHash($keyHash);

        if (null === $apiKey) {
            throw GatewayException::authenticationFailed('Invalid API key.');
        }

        if (!$apiKey->isEnabled()) {
            throw GatewayException::authenticationFailed(sprintf('API key "%s" is disabled.', $apiKey->getName()));
        }

        if ($apiKey->isExpired()) {
            throw GatewayException::authenticationFailed(sprintf('API key "%s" has expired.', $apiKey->getName()));
        }

        $teamRules = $apiKey->getTeam()?->getRules() ?? [];
        $keyRules = $apiKey->getOverrides() ?? [];

        $rules = new ResolvedRules(
            allowedModels: $this->mergeAllowedModels($teamRules['allowed_models'] ?? null, $keyRules['allowed_models'] ?? null),
            blockedModels: $this->mergeBlockedModels($teamRules['blocked_models'] ?? null, $keyRules['blocked_models'] ?? null),
            budgetPerDay: $this->mostRestrictiveFloat($teamRules['budget_per_day'] ?? null, $keyRules['budget_per_day'] ?? null),
            budgetPerMonth: $this->mostRestrictiveFloat($teamRules['budget_per_month'] ?? null, $keyRules['budget_per_month'] ?? null),
            rateLimitPerMinute: $this->mostRestrictiveInt($teamRules['rate_limit_per_minute'] ?? null, $keyRules['rate_limit_per_minute'] ?? null),
            rateLimitPerDay: $this->mostRestrictiveInt($teamRules['rate_limit_per_day'] ?? null, $keyRules['rate_limit_per_day'] ?? null),
        );

        return new ApiKeyContext($apiKey, $rules);
    }

    private function mergeAllowedModels(?array $teamModels, ?array $keyModels): ?array
    {
        if (null === $teamModels) {
            return $keyModels;
        }

        if (null === $keyModels) {
            return $teamModels;
        }

        return array_values(array_intersect($teamModels, $keyModels));
    }

    private function mergeBlockedModels(?array $teamModels, ?array $keyModels): ?array
    {
        if (null === $teamModels) {
            return $keyModels;
        }

        if (null === $keyModels) {
            return $teamModels;
        }

        return array_values(array_unique([...$teamModels, ...$keyModels]));
    }

    private function mostRestrictiveFloat(mixed $teamLimit, mixed $keyLimit): ?float
    {
        if (null === $teamLimit) {
            return null === $keyLimit ? null : (float) $keyLimit;
        }

        if (null === $keyLimit) {
            return (float) $teamLimit;
        }

        return min((float) $teamLimit, (float) $keyLimit);
    }

    private function mostRestrictiveInt(mixed $teamLimit, mixed $keyLimit): ?int
    {
        if (null === $teamLimit) {
            return null === $keyLimit ? null : (int) $keyLimit;
        }

        if (null === $keyLimit) {
            return (int) $teamLimit;
        }

        return min((int) $teamLimit, (int) $keyLimit);
    }
}
