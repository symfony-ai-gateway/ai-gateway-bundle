<?php

declare(strict_types=1);

namespace AIGateway\Tests\Unit;

use AIGateway\Auth\KeyRuleScopeValidator;
use PHPUnit\Framework\TestCase;

final class KeyRuleScopeValidatorTest extends TestCase
{
    public function test_accepts_key_rules_inside_team_scope(): void
    {
        $errors = (new KeyRuleScopeValidator())->validate(
            ['budget_per_day' => 10, 'rate_limit_per_day' => 100, 'allowed_models' => ['glm', 'qwen']],
            ['budget_per_day' => 5, 'rate_limit_per_day' => 50, 'allowed_models' => ['glm']],
        );

        self::assertSame([], $errors);
    }

    public function test_rejects_key_rules_outside_team_scope(): void
    {
        $errors = (new KeyRuleScopeValidator())->validate(
            ['budget_per_day' => 10, 'rate_limit_per_day' => 100, 'allowed_models' => ['glm']],
            ['budget_per_day' => 20, 'rate_limit_per_day' => 200, 'allowed_models' => ['glm', 'gpt-4']],
        );

        self::assertContains('Budget / Day cannot exceed team limit (10).', $errors);
        self::assertContains('Rate Limit / Day cannot exceed team limit (100).', $errors);
        self::assertContains('Models outside team scope: gpt-4', $errors);
    }

    public function test_rejects_negative_limits(): void
    {
        $errors = (new KeyRuleScopeValidator())->validate([], ['budget_per_day' => -1]);

        self::assertSame(['Budget / Day cannot be negative.'], $errors);
    }
}
