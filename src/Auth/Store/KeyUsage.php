<?php

declare(strict_types=1);

namespace AIGateway\Auth\Store;

/**
 * Aggregated usage statistics (requests, tokens, cost) for a key over a period.
 */
final class KeyUsage
{
    public function __construct(
        public readonly int $requests = 0,
        public readonly int $tokens = 0,
        public readonly float $costUsd = 0.0,
    ) {}
}
