<?php

declare(strict_types=1);

namespace AIGateway\Tests\Unit;

use AIGateway\Routing\ChainWeightNormalizer;
use PHPUnit\Framework\TestCase;

final class ChainWeightNormalizerTest extends TestCase
{
    public function test_normalizes_weights_to_one_hundred(): void
    {
        $normalized = (new ChainWeightNormalizer())->normalize([10 => 30, 11 => 30, 12 => 30]);

        self::assertSame(100, array_sum($normalized));
        self::assertSame([10 => 34, 11 => 33, 12 => 33], $normalized);
    }

    public function test_spreads_zero_weights_evenly(): void
    {
        self::assertSame([10 => 34, 11 => 33, 12 => 33], (new ChainWeightNormalizer())->normalize([10 => 0, 11 => 0, 12 => 0]));
    }

    public function test_clamps_submitted_weights(): void
    {
        $normalized = (new ChainWeightNormalizer())->normalize([10 => 200, 11 => -5]);

        self::assertSame([10 => 100, 11 => 0], $normalized);
    }
}
