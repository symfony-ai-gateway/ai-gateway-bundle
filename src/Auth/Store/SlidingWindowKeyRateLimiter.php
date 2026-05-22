<?php

declare(strict_types=1);

namespace AIGateway\Auth\Store;

final class SlidingWindowKeyRateLimiter
{
    /** @var array<string, list<int>> */
    private array $windows = [];
    public function isAllowed(string $keyId, int $limit): object
    {
        $now = time();
        $windowStart = $now - 60;

        if (!isset($this->windows[$keyId])) {
            $this->windows[$keyId] = [];
        }

        $this->windows[$keyId] = array_values(array_filter(
            $this->windows[$keyId],
            static fn(int $ts): bool => $ts > $windowStart,
        ));

        $count = count($this->windows[$keyId]);

        return (object) [
            'allowed' => $count < $limit,
            'limit' => $limit,
            'remaining' => max(0, $limit - $count),
            'resetAt' => $windowStart + 60,
        ];
    }

    public function increment(string $keyId): void
    {
        $this->windows[$keyId][] = time();
    }
}
