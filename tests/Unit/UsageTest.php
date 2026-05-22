<?php

declare(strict_types=1);

namespace AIGateway\Tests\Unit;

use AIGateway\Core\Usage;
use PHPUnit\Framework\TestCase;

final class UsageTest extends TestCase
{
    public function test_defaults_to_zero(): void
    {
        $usage = new Usage();
        self::assertSame(0, $usage->promptTokens);
        self::assertSame(0, $usage->completionTokens);
        self::assertSame(0, $usage->totalTokens);
    }

    public function test_constructor_sets_values(): void
    {
        $usage = new Usage(promptTokens: 10, completionTokens: 20, totalTokens: 30);
        self::assertSame(10, $usage->promptTokens);
        self::assertSame(20, $usage->completionTokens);
        self::assertSame(30, $usage->totalTokens);
    }
}
