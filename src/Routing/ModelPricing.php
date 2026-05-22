<?php

declare(strict_types=1);

namespace AIGateway\Routing;

/**
 * Pricing helper used to estimate request cost from token usage.
 */
final readonly class ModelPricing
{
    public function __construct(
        public float $inputPerMillion = 0.0,
        public float $outputPerMillion = 0.0,
    ) {}

    /**
     * Compute USD cost from prompt and completion token counts.
     */
    public function calculateCost(int $promptTokens, int $completionTokens): float
    {
        return ($this->inputPerMillion * $promptTokens / 1_000_000)
            + ($this->outputPerMillion * $completionTokens / 1_000_000);
    }
}
