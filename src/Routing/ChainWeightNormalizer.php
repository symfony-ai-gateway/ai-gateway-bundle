<?php

declare(strict_types=1);

namespace AIGateway\Routing;

final class ChainWeightNormalizer
{
    /** @param array<int,int> $weights */
    public function normalize(array $weights): array
    {
        if ([] === $weights) {
            return [];
        }

        $weights = array_map(static fn (int|float|string $weight): int => max(0, min(100, (int) $weight)), $weights);
        $total = array_sum($weights);
        if ($total <= 0) {
            return $this->spreadEvenly(array_keys($weights));
        }

        $normalized = [];
        $fractions = [];
        foreach ($weights as $id => $weight) {
            $exact = ($weight / $total) * 100;
            $normalized[$id] = (int) floor($exact);
            $fractions[$id] = $exact - $normalized[$id];
        }

        arsort($fractions);
        $remaining = 100 - array_sum($normalized);
        foreach (array_keys($fractions) as $id) {
            if ($remaining <= 0) {
                break;
            }
            ++$normalized[$id];
            --$remaining;
        }

        return $normalized;
    }

    private function spreadEvenly(array $ids): array
    {
        $count = count($ids);
        $base = intdiv(100, $count);
        $remainder = 100 - ($base * $count);
        $normalized = [];
        foreach ($ids as $id) {
            $normalized[$id] = $base + ($remainder-- > 0 ? 1 : 0);
        }

        return $normalized;
    }
}
