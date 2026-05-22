<?php

declare(strict_types=1);

namespace AIGateway\Tests\Integration;

use AIGateway\Catalog\GatewayCatalog;
use AIGateway\Exception\GatewayException;
use AIGateway\Routing\ModelRegistry;
use AIGateway\Routing\ModelPricing;
use AIGateway\Routing\ModelResolution;
use PHPUnit\Framework\TestCase;

/**
 * Integration-style tests for ModelRegistry + Catalog interaction.
 */
final class ModelRegistryTest extends TestCase
{
    private ModelRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new ModelRegistry([
            'deepseek' => [
                'provider' => 'opencode',
                'model' => 'deepseek-v4-flash',
                'pricing' => ['input' => 0.0, 'output' => 0.0],
            ],
            'claude-sonnet' => [
                'provider' => 'anthropic',
                'model' => 'claude-sonnet-4-20250514',
                'format' => 'anthropic',
                'pricing' => ['input' => 5.0, 'output' => 15.0],
            ],
        ]);
    }

    public function test_resolves_static_model(): void
    {
        $res = $this->registry->resolve('deepseek');
        self::assertSame('deepseek', $res->alias);
        self::assertSame('opencode', $res->provider);
        self::assertSame('deepseek-v4-flash', $res->model);
        self::assertSame(0.0, $res->pricing->inputPerMillion);
    }

    public function test_resolves_static_model_with_pricing(): void
    {
        $res = $this->registry->resolve('claude-sonnet');
        self::assertSame('claude-sonnet', $res->alias);
        self::assertSame('anthropic', $res->provider);
        self::assertSame('claude-sonnet-4-20250514', $res->model);
        self::assertSame(5.0, $res->pricing->inputPerMillion);
        self::assertSame(15.0, $res->pricing->outputPerMillion);
    }

    public function test_has_returns_true_for_existing(): void
    {
        self::assertTrue($this->registry->has('deepseek'));
    }

    public function test_has_returns_false_for_unknown(): void
    {
        self::assertFalse($this->registry->has('unknown'));
    }

    public function test_resolve_throws_for_unknown(): void
    {
        $this->expectException(GatewayException::class);
        $this->registry->resolve('unknown');
    }
}
