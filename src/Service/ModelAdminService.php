<?php

declare(strict_types=1);

namespace AIGateway\Service;

use AIGateway\Catalog\GatewayCatalog;
use AIGateway\Logging\RequestLogStore;

/**
 * Encapsulates dashboard operations around model aliases.
 */
final class ModelAdminService
{
    public function __construct(
        private readonly ?GatewayCatalog $catalog = null,
        private readonly ?RequestLogStore $requestLogStore = null,
    ) {}

    /**
     * Return every model alias enriched with usage statistics.
     * @return list<array{alias:string,provider_name:string,model:string,stats:array}>
     */
    /** Execute the operation. */
    public function list(): array
    {
        $models = $this->catalog?->listModels() ?? [];
        $stats = $this->indexByLabel($this->requestLogStore?->getBreakdown('pick_alias', [], 200) ?? []);
        foreach ($models as &$model) {
            $model['stats'] = $stats[$model['alias']] ?? ['requests' => 0, 'tokens' => 0, 'cost' => 0, 'errors' => 0, 'avg_duration_ms' => 0];
        }

        return $models;
    }

    /** Execute the operation. */
    public function providers(): array
    {
        return $this->catalog?->listProviders() ?? [];
    }

    /** Execute the operation. */
    public function find(string $alias): ?array
    {
        return $this->catalog?->getModel($alias);
    }

    /** Execute the operation. */
    public function save(string $alias, string $providerName, string $model, float $pricingInput, float $pricingOutput): void
    {
        $this->catalog?->saveModel($alias, $providerName, $model, $pricingInput, $pricingOutput);
    }

    /** Execute the operation. */
    public function renameAndSave(string $currentAlias, string $newAlias, string $providerName, string $model, float $pricingInput, float $pricingOutput): void
    {
        if ($newAlias !== $currentAlias) {
            $this->catalog?->deleteModel($currentAlias);
        }

        $this->catalog?->saveModel($newAlias, $providerName, $model, $pricingInput, $pricingOutput);
    }

    /** Execute the operation. */
    public function delete(string $alias): void
    {
        $this->catalog?->deleteModel($alias);
    }

    /** Execute the operation. */
    public function detail(string $alias): ?array
    {
        $model = $this->catalog?->getModel($alias);
        if (null === $model) {
            return null;
        }

        $filters = ['pickAlias' => $alias];

        return [
            'model' => $model,
            'model_stats' => $this->compactStats($this->requestLogStore?->getModelStats($alias) ?? $this->emptyStats()),
            'status_breakdown' => $this->requestLogStore?->getStatusBreakdown($filters) ?? [],
            'daily_usage' => $this->requestLogStore?->getDailyUsage(60, $filters) ?? [],
            'team_breakdown' => $this->requestLogStore?->getBreakdown('team', $filters, 15) ?? [],
            'key_breakdown' => $this->requestLogStore?->getBreakdown('key', $filters, 15) ?? [],
            'recent_logs' => $this->requestLogStore?->getRecentLogsForModel($alias, 50) ?? [],
            'model_chains' => $this->findChainsUsingModel($alias),
        ];
    }

    /** Execute the operation. */
    private function findChainsUsingModel(string $alias): array
    {
        $matches = [];
        foreach ($this->catalog?->listChains() ?? [] as $chain) {
            foreach ($this->catalog?->getChainSteps($chain['alias']) ?? [] as $step) {
                if (($step['model_alias'] ?? null) === $alias) {
                    $matches[] = $chain;
                    break;
                }
            }
        }

        return $matches;
    }

    /** Execute the operation. */
    private function emptyStats(): array
    {
        return [
            'total_requests' => 0,
            'total_prompt_tokens' => 0,
            'total_completion_tokens' => 0,
            'total_cost' => 0.0,
            'avg_duration_ms' => 0.0,
            'total_errors' => 0,
        ];
    }

    /** Execute the operation. */
    private function compactStats(array $stats): array
    {
        return [
            'requests' => $stats['total_requests'] ?? 0,
            'tokens' => ($stats['total_prompt_tokens'] ?? 0) + ($stats['total_completion_tokens'] ?? 0),
            'cost' => $stats['total_cost'] ?? 0.0,
            'errors' => $stats['total_errors'] ?? 0,
            'avg_duration_ms' => $stats['avg_duration_ms'] ?? 0.0,
        ];
    }

    /** Execute the operation. */
    private function indexByLabel(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row['label']] = $row;
        }

        return $indexed;
    }
}
