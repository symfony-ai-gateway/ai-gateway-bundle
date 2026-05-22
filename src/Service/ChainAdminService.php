<?php

declare(strict_types=1);

namespace AIGateway\Service;

use AIGateway\Catalog\GatewayCatalog;
use AIGateway\Logging\RequestLogStore;
use AIGateway\Routing\ChainWeightNormalizer;

/**
 * Encapsulates dashboard operations around model chains.
 */
final class ChainAdminService
{
    public function __construct(
        private readonly ?GatewayCatalog $catalog = null,
        private readonly ?RequestLogStore $requestLogStore = null,
        private readonly ChainWeightNormalizer $weightNormalizer = new ChainWeightNormalizer(),
    ) {}


    /**
     * Return every chain with step counts and usage statistics.
     * @return list<array{alias:string,step_count:int,stats:array}>
     */
    public function list(): array
    {
        $chains = $this->catalog?->listChains() ?? [];
        $stats = $this->indexByLabel($this->requestLogStore?->getBreakdown('model', [], 200) ?? []);
        foreach ($chains as &$chain) {
            $chain['stats'] = $stats[$chain['alias']] ?? ['requests' => 0, 'tokens' => 0, 'cost' => 0, 'errors' => 0, 'avg_duration_ms' => 0];
            $chain['step_count'] = count($this->catalog?->getChainSteps($chain['alias']) ?? []);
        }

        return $chains;
    }


    /** Create a new chain alias. */
    public function create(string $alias): void
    {
        $this->catalog?->saveChain($alias);
    }


    /** Delete a chain and all of its steps. */
    public function delete(string $alias): void
    {
        $this->catalog?->deleteChain($alias);
    }


    /** Append a model alias as a new step in a chain. */
    public function addStep(string $alias, string $modelAlias, int $priority, int $weight): void
    {
        $this->catalog?->addChainStep($alias, $modelAlias, $priority, max(0, min(100, $weight)));
        $this->normalizePriorityWeights($alias, $priority);
    }


    /** Remove one step from a chain. */
    public function removeStep(int $id): void
    {
        $this->catalog?->removeChainStep($id);
    }


    /** Persist updated weights for all steps in a chain. */
    public function saveWeights(string $alias, array $weights): void
    {
        $clean = [];
        foreach ($weights as $id => $weight) {
            $clean[(int) $id] = (int) $weight;
        }

        $this->catalog?->updateChainWeights($alias, $this->normalizeSubmittedWeights($alias, $clean));
    }


    /** Full detail data for a chain including step list and breakdowns. */
    public function detail(string $alias): ?array
    {
        $chain = $this->catalog?->getChain($alias);
        if (null === $chain) {
            return null;
        }

        $filters = ['model' => $alias];

        return [
            'chain' => $chain,
            'steps' => $this->catalog?->getChainSteps($alias) ?? [],
            'models' => $this->catalog?->listModels() ?? [],
            'chain_stats' => $this->compactStats($this->requestLogStore?->getChainStats($alias) ?? $this->emptyStats()),
            'status_breakdown' => $this->requestLogStore?->getStatusBreakdown($filters) ?? [],
            'daily_usage' => $this->requestLogStore?->getDailyUsage(60, $filters) ?? [],
            'provider_breakdown' => $this->requestLogStore?->getBreakdown('provider', $filters, 15) ?? [],
            'resolved_model_breakdown' => $this->requestLogStore?->getBreakdown('pick_alias', $filters, 15) ?? [],
            'pick_alias_breakdown' => $this->requestLogStore?->getBreakdown('pick_alias', $filters, 15) ?? [],
            'key_breakdown' => $this->requestLogStore?->getBreakdown('key', $filters, 15) ?? [],
            'team_breakdown' => $this->requestLogStore?->getBreakdown('team', $filters, 15) ?? [],
            'recent_logs' => $this->requestLogStore?->getRecentLogRows(50, $filters) ?? [],
            'weights_error' => false,
        ];
    }

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

    private function indexByLabel(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row['label']] = $row;
        }

        return $indexed;
    }

    private function normalizePriorityWeights(string $alias, int $priority): void
    {
        $steps = array_filter($this->catalog?->getChainSteps($alias) ?? [], static fn (array $step): bool => (int) $step['priority'] === $priority);
        $weights = [];
        foreach ($steps as $step) {
            $weights[(int) $step['id']] = (int) $step['weight'];
        }

        $this->catalog?->updateChainWeights($alias, $this->weightNormalizer->normalize($weights));
    }

    private function normalizeSubmittedWeights(string $alias, array $weights): array
    {
        $byPriority = [];
        foreach ($this->catalog?->getChainSteps($alias) ?? [] as $step) {
            $id = (int) $step['id'];
            $priority = (int) $step['priority'];
            $byPriority[$priority][$id] = $weights[$id] ?? (int) $step['weight'];
        }

        $normalized = [];
        foreach ($byPriority as $priorityWeights) {
            $normalized += $this->weightNormalizer->normalize($priorityWeights);
        }

        return $normalized;
    }
}
