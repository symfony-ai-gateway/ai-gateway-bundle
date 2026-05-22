<?php

declare(strict_types=1);

namespace AIGateway\Controller\Gateway;

use AIGateway\Catalog\GatewayCatalog;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Gateway public introspection endpoints.
 */
final class InfoController
{
    public function __construct(
        private readonly ?GatewayCatalog $catalog = null,
    ) {}

    #[Route('/openai/v1/models', name: 'ai_gateway_openai_models', methods: ['GET'])]
    public function openaiModels(): JsonResponse
    {
        return $this->modelsByFormat('openai');
    }

    #[Route('/anthropic/v1/models', name: 'ai_gateway_anthropic_models', methods: ['GET'])]
    public function anthropicModels(): JsonResponse
    {
        return $this->modelsByFormat('anthropic');
    }

    #[Route('/v1/health', name: 'ai_gateway_health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        return new JsonResponse([
            'status' => 'ok',
            'providers_configured' => count($this->catalog?->listProviders() ?? []) > 0,
            'models_available' => count($this->catalog?->listModels() ?? []) + count($this->catalog?->listChains() ?? []),
        ]);
    }

    /**
     * List models and chains compatible with the given provider format.
     *
     * Models are filtered by format. Chains are included in all format listings
     * since they are format-neutral identifiers resolved at request time.
     */
    private function modelsByFormat(string $format): JsonResponse
    {
        $models = $this->catalog?->listModels() ?? [];
        $chains = $this->catalog?->listChains() ?? [];

        $data = [];
        foreach ($models as $model) {
            if (($model['format'] ?? '') !== $format) {
                continue;
            }
            $data[] = ['id' => $model['alias'], 'object' => 'model', 'owned_by' => 'aigateway'];
        }
        // Include all chains in both listings (format-neutral)
        foreach ($chains as $chain) {
            $data[] = ['id' => $chain['alias'], 'object' => 'chain', 'owned_by' => 'aigateway'];
        }

        return new JsonResponse(['object' => 'list', 'data' => $data]);
    }
}
