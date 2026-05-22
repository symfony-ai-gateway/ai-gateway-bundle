<?php

declare(strict_types=1);

namespace AIGateway\Auth;

/**
 * Merged budget and model-access rules that apply to a single request.
 *
 * These rules are built at authentication time by combining the key-level
 * overrides (set on the key editing page) with the team-level defaults.
 */
final readonly class ResolvedRules
{
    public function __construct(
        /** If set, only these model aliases may be used. null = no restriction. */
        public ?array $allowedModels = null,
        /** If set, these model aliases are rejected regardless of allowed list. */
        public ?array $blockedModels = null,
        /** Maximum USD that can be spent in a single day. */
        public ?float $budgetPerDay = null,
        /** Maximum USD that can be spent in a calendar month. */
        public ?float $budgetPerMonth = null,
        /** Maximum requests allowed per rolling 60-second window. */
        public ?int $rateLimitPerMinute = null,
        /** Maximum requests allowed per day. */
        public ?int $rateLimitPerDay = null,
    ) {}

    /**
     * Check whether a model alias passes the allowed/blocked filters.
     */
    public function isModelAllowed(string $model): bool
    {
        if (null !== $this->blockedModels && in_array($model, $this->blockedModels, true)) {
            return false;
        }
        if (null !== $this->allowedModels) {
            return in_array($model, $this->allowedModels, true);
        }
        return true;
    }
}
