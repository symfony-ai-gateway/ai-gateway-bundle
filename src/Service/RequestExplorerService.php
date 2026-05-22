<?php

declare(strict_types=1);

namespace AIGateway\Service;

use AIGateway\Auth\Store\KeyStoreInterface;
use AIGateway\Catalog\GatewayCatalog;
use AIGateway\Logging\RequestLogStore;

/**
 * Builds request list and analytics datasets for the dashboard.
 */
final class RequestExplorerService
{
    public function __construct(
        private readonly ?GatewayCatalog $catalog = null,
        private readonly ?KeyStoreInterface $keyStore = null,
        private readonly ?RequestLogStore $requestLogStore = null,
    ) {}


    /** Execute the operation. */
    public function requests(array $filters, array $rawFilters): array
    {
        return [
            'filters' => $rawFilters,
            'providers' => $this->catalog?->listProviders() ?? [],
            'models' => $this->catalog?->listModels() ?? [],
            'keys' => $this->keyStore?->listKeys() ?? [],
            'teams' => $this->keyStore?->listTeams() ?? [],
            'stats' => $this->requestLogStore?->getStats($filters) ?? $this->emptyStats(),
            'status_breakdown' => $this->requestLogStore?->getStatusBreakdown($filters) ?? [],
            'provider_breakdown' => $this->requestLogStore?->getBreakdown('provider', $filters, 12) ?? [],
            'model_breakdown' => $this->requestLogStore?->getBreakdown('pick_alias', $filters, 12) ?? [],
            'key_breakdown' => $this->requestLogStore?->getBreakdown('key', $filters, 12) ?? [],
            'team_breakdown' => $this->requestLogStore?->getBreakdown('team', $filters, 12) ?? [],
            'daily_usage' => $this->requestLogStore?->getDailyUsage(30, $filters) ?? [],
            'logs' => $this->requestLogStore?->getRecentLogRows(200, $filters) ?? [],
        ];
    }


    /** Execute the operation. */
    public function analytics(): array
    {
        return [
            'stats' => $this->requestLogStore?->getStats() ?? $this->emptyStats(),
            'daily_usage' => $this->requestLogStore?->getDailyUsage(60) ?? [],
            'status_breakdown' => $this->requestLogStore?->getStatusBreakdown() ?? [],
            'provider_breakdown' => $this->requestLogStore?->getBreakdown('provider', [], 12) ?? [],
            'model_breakdown' => $this->requestLogStore?->getBreakdown('pick_alias', [], 12) ?? [],
            'key_breakdown' => $this->requestLogStore?->getBreakdown('key', [], 12) ?? [],
            'team_breakdown' => $this->requestLogStore?->getBreakdown('team', [], 12) ?? [],
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
