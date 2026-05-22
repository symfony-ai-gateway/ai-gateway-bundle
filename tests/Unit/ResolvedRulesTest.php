<?php

declare(strict_types=1);

namespace AIGateway\Tests\Unit;

use AIGateway\Auth\ResolvedRules;
use PHPUnit\Framework\TestCase;

final class ResolvedRulesTest extends TestCase
{
    public function test_allows_by_default(): void
    {
        $rules = new ResolvedRules();
        self::assertTrue($rules->isModelAllowed('deepseek'));
        self::assertTrue($rules->isModelAllowed('anything'));
    }

    public function test_blocks_specific_models(): void
    {
        $rules = new ResolvedRules(blockedModels: ['gpt-4']);
        self::assertFalse($rules->isModelAllowed('gpt-4'));
        self::assertTrue($rules->isModelAllowed('deepseek'));
    }

    public function test_allowlist_restricts(): void
    {
        $rules = new ResolvedRules(allowedModels: ['deepseek', 'qwen']);
        self::assertTrue($rules->isModelAllowed('deepseek'));
        self::assertTrue($rules->isModelAllowed('qwen'));
        self::assertFalse($rules->isModelAllowed('gpt-4'));
    }

    public function test_blocklist_overrides_allowlist(): void
    {
        $rules = new ResolvedRules(allowedModels: ['deepseek'], blockedModels: ['deepseek']);
        self::assertFalse($rules->isModelAllowed('deepseek'));
    }
}
