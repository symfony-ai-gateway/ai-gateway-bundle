<?php

declare(strict_types=1);

namespace AIGateway\Service;

use AIGateway\Catalog\GatewayCatalog;
use AIGateway\Logging\RequestLogStore;

/**
 * Encapsulates dashboard operations around provider definitions.
 */
final class ProviderAdminService
{
    public function __construct(
        private readonly ?GatewayCatalog $catalog = null,
        private readonly ?RequestLogStore $requestLogStore = null,
    ) {}

    /**
     * Return every provider enriched with usage statistics and model count.
     * @return list<array{name:string,format:string,stats:array,model_count:int}>
     */
    /** Execute the operation. */
    public function list(): array
    {
        $providers = $this->catalog?->listProviders() ?? [];
        $models = $this->catalog?->listModels() ?? [];
        $stats = $this->indexByLabel($this->requestLogStore?->getBreakdown('provider', [], 100) ?? []);
        $modelCounts = [];

        foreach ($models as $model) {
            $modelCounts[$model['provider_name']] = ($modelCounts[$model['provider_name']] ?? 0) + 1;
        }

        foreach ($providers as &$provider) {
            $provider['stats'] = $stats[$provider['name']] ?? ['requests' => 0, 'tokens' => 0, 'cost' => 0, 'errors' => 0, 'avg_duration_ms' => 0];
            $provider['model_count'] = $modelCounts[$provider['name']] ?? 0;
        }

        return $providers;
    }

    /**
     * Find one provider by name.
     * @return array{name:string,format:string,api_key:string,base_url:string,completions_path:string}|null
     */
    /** Execute the operation. */
    public function find(string $name): ?array
    {
        return $this->catalog?->getProvider($name);
    }

    /**
     * Create or update a provider definition.
     */
    /** Execute the operation. */
    public function save(string $name, string $format, string $apiKey, ?string $baseUrl, string $completionsPath): void
    {
        $this->catalog?->saveProvider($name, $format, $apiKey, $baseUrl, $completionsPath);
    }

    /**
     * Delete a provider and its attached model aliases.
     */
    /** Execute the operation. */
    public function delete(string $name): void
    {
        $this->catalog?->deleteProvider($name);
    }

    /**
     * Full detail data for a single provider, including logs, breakdowns, and daily usage.
     * @return array{provider:array,provider_stats:array,status_breakdown:array,daily_usage:array,model_breakdown:array,...}|null
     */
    /** Execute the operation. */
    public function detail(string $name): ?array
    {
        $provider = $this->catalog?->getProvider($name);
        if (null === $provider) {
            return null;
        }

        $filters = ['provider' => $name];

        return [
            'provider' => $provider,
            'provider_stats' => $this->compactStats($this->requestLogStore?->getProviderStats($name) ?? $this->emptyStats()),
            'status_breakdown' => $this->requestLogStore?->getStatusBreakdown($filters) ?? [],
            'daily_usage' => $this->requestLogStore?->getDailyUsage(60, $filters) ?? [],
            'model_breakdown' => $this->requestLogStore?->getBreakdown('pick_alias', $filters, 20) ?? [],
            'resolved_model_breakdown' => $this->requestLogStore?->getBreakdown('pick_alias', $filters, 20) ?? [],
            'top_keys' => $this->requestLogStore?->getBreakdown('key', $filters, 15) ?? [],
            'top_teams' => $this->requestLogStore?->getBreakdown('team', $filters, 15) ?? [],
            'recent_logs' => $this->requestLogStore?->getRecentLogsForProvider($name, 50) ?? [],
        ];
    }

    /** @return array{total_requests:int,total_prompt_tokens:int,total_completion_tokens:int,total_cost:float,avg_duration_ms:float,total_errors:int} */
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

    /** @return array{requests:int,tokens:int,cost:float,errors:int,avg_duration_ms:float} */
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

    /** @return array<string, array> */
    private function indexByLabel(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row['label']] = $row;
        }

        return $indexed;
    }
}
