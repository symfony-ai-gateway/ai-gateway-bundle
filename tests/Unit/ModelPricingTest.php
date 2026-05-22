<?php

declare(strict_types=1);

namespace AIGateway\Tests\Unit;

use AIGateway\Routing\ModelPricing;
use AIGateway\Routing\ModelResolution;
use PHPUnit\Framework\TestCase;

final class ModelPricingTest extends TestCase
{
    public function test_calculate_zero_cost(): void
    {
        $pricing = new ModelPricing();
        self::assertSame(0.0, $pricing->calculateCost(0, 0));
    }

    public function test_calculate_cost(): void
    {
        $pricing = new ModelPricing(inputPerMillion: 1.0, outputPerMillion: 3.0);
        $cost = $pricing->calculateCost(1000, 500);
        // 1000 prompt tokens at $1/M = $0.001
        // 500 completion tokens at $3/M = $0.0015
        self::assertSame(0.0025, $cost);
    }

    public function test_calculate_with_more_tokens(): void
    {
        $pricing = new ModelPricing(inputPerMillion: 5.0, outputPerMillion: 15.0);
        $cost = $pricing->calculateCost(200000, 100000);
        // 200k prompt at $5/M = $1.0
        // 100k completion at $15/M = $1.5
        self::assertSame(2.5, $cost);
    }
}

final class ModelResolutionTest extends TestCase
{
    public function test_resolution_holds_all_fields(): void
    {
        $pricing = new ModelPricing(inputPerMillion: 1.0, outputPerMillion: 2.0);
        $res = new ModelResolution(
            alias: 'deepseek',
            provider: 'opencode',
            providerFormat: 'openai',
            model: 'deepseek-v4-flash',
            pricing: $pricing,
        );

        self::assertSame('deepseek', $res->alias);
        self::assertSame('opencode', $res->provider);
        self::assertSame('openai', $res->providerFormat);
        self::assertSame('deepseek-v4-flash', $res->model);
        self::assertSame(1.0, $res->pricing->inputPerMillion);
    }
}
