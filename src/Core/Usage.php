<?php

declare(strict_types=1);

namespace AIGateway\Core;

/**
 * Token usage tuple: prompt, completion, and total token counts.
 */
final class Usage
{
    public function __construct(
        public readonly int $promptTokens = 0,
        public readonly int $completionTokens = 0,
        public readonly int $totalTokens = 0,
    ) {}
}
