<?php

declare(strict_types=1);

namespace AIGateway\Auth;

use AIGateway\Entity\ApiKey;

/**
 * Result of a successful authentication, carrying the resolved API key and the
 * effective budget/rate-limit rules (merged from key-level overrides and
 * team-level rules).
 */
final class ApiKeyContext
{
    public function __construct(
        /** The resolved API key entity (hash matched against the store). */
        public readonly ApiKey $apiKey,
        /** Resolved rules that apply to this request (overrides + team rules). */
        public readonly ResolvedRules $resolvedRules,
    ) {}
}
