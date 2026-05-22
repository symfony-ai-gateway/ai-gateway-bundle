<?php

declare(strict_types=1);

namespace AIGateway\Service;

use AIGateway\Auth\Store\KeyStoreInterface;
use AIGateway\Catalog\GatewayCatalog;
use AIGateway\Entity\ApiKey;
use AIGateway\Logging\RequestLogStore;

/**
 * Builds the data shown on the dashboard overview page.
 */
final class DashboardOverviewService
{
    public function __construct(
        private readonly ?KeyStoreInterface $keyStore = null,
        private readonly ?GatewayCatalog $catalog = null,
        private readonly ?RequestLogStore $requestLogStore = null,
    ) {}

    /**
     * Aggregate global KPIs and recent activity for the overview page.
     */

    /** Execute the operation. */
    public function overview(): array
    {
        $keys = $this->keyStore?->listKeys() ?? [];
        $teams = $this->keyStore?->listTeams() ?? [];
        $providers = $this->catalog?->listProviders() ?? [];
        $models = $this->catalog?->listModels() ?? [];
        $stats = $this->requestLogStore?->getStats() ?? $this->emptyStats();

        return [
            'total_keys' => count($keys),
            'active_keys' => count(array_filter($keys, static fn (ApiKey $key): bool => $key->isEnabled())),
            'total_teams' => count($teams),
            'total_providers' => count($providers),
            'total_models' => count($models),
            'total_requests' => $stats['total_requests'],
            'total_errors' => $stats['total_errors'],
            'total_cost' => $stats['total_cost'],
            'total_tokens' => $stats['total_prompt_tokens'] + $stats['total_completion_tokens'],
            'avg_duration' => $stats['avg_duration_ms'],
            'daily_usage' => $this->requestLogStore?->getDailyUsage(30) ?? [],
            'top_models' => $this->requestLogStore?->getBreakdown('pick_alias', [], 8) ?? [],
            'top_keys' => $this->requestLogStore?->getBreakdown('key', [], 8) ?? [],
            'top_providers' => $this->requestLogStore?->getBreakdown('provider', [], 8) ?? [],
            'top_teams' => $this->requestLogStore?->getBreakdown('team', [], 8) ?? [],
            'status_breakdown' => $this->requestLogStore?->getStatusBreakdown() ?? [],
            'recent_logs' => $this->requestLogStore?->getRecentLogRows(20) ?? [],
        ];
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
}
