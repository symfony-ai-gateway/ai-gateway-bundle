<?php

declare(strict_types=1);

namespace AIGateway\Routing;

use AIGateway\Exception\GatewayException;

/**
 * In-memory registry for statically declared model aliases.
 */
final class ModelRegistry
{
    /** @var array<string, ModelResolution> */
    private array $resolutions = [];

    /**
     * @param array<string, array{provider: string, model: string, pricing?: array{input?: float, output?: float}}> $config
     */
    public function __construct(array $config = [])
    {
        foreach ($config as $alias => $modelConfig) {
            $this->resolutions[$alias] = new ModelResolution(
                alias: $alias,
                provider: $modelConfig['provider'],
                providerFormat: $modelConfig['format'] ?? 'openai',
                model: $modelConfig['model'],
                pricing: new ModelPricing(
                    inputPerMillion: $modelConfig['pricing']['input'] ?? 0.0,
                    outputPerMillion: $modelConfig['pricing']['output'] ?? 0.0,
                ),
            );
        }
    }

    /**
     * Check whether a static alias exists.
     */
    public function has(string $alias): bool
    {
        return isset($this->resolutions[$alias]);
    }

    /**
     * Resolve one static alias or throw a gateway error.
     */
    public function resolve(string $alias): ModelResolution
    {
        return $this->resolutions[$alias] ?? throw GatewayException::modelNotFound($alias, array_keys($this->resolutions));
    }
}
