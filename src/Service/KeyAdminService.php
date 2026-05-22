<?php

declare(strict_types=1);

namespace AIGateway\Service;

use AIGateway\Auth\Store\KeyStoreInterface;
use AIGateway\Auth\KeyRuleScopeValidator;
use AIGateway\Catalog\GatewayCatalog;
use AIGateway\Logging\RequestLogStore;

/**
 * Encapsulates dashboard operations around gateway API keys.
 */
final class KeyAdminService
{
    public function __construct(
        private readonly ?KeyStoreInterface $keyStore = null,
        private readonly ?GatewayCatalog $catalog = null,
        private readonly ?RequestLogStore $requestLogStore = null,
        private readonly KeyRuleScopeValidator $scopeValidator = new KeyRuleScopeValidator(),
    ) {}


    /**
     * Return every key with team names and usage statistics.
     * @return array{keys:list<ApiKey>,team_names:array<int,string>,key_stats:array<int,array>}
     */
    public function list(): array
    {
        $keys = $this->keyStore?->listKeys() ?? [];
        $stats = [];
        foreach ($keys as $key) {
            if (null !== $key->getId()) {
                $stats[$key->getId()] = $this->requestLogStore?->getKeyStats($key->getId()) ?? ['requests' => 0, 'tokens' => 0, 'cost' => 0, 'errors' => 0, 'avg_duration_ms' => 0];
            }
        }

        $teamNames = [];
        foreach ($this->keyStore?->listTeams() ?? [] as $team) {
            $teamNames[$team->getId() ?? 0] = $team->getName();
        }

        return ['keys' => $keys, 'team_names' => $teamNames, 'key_stats' => $stats];
    }


    /**
     * Generate a new API key and return the raw token (shown once to the user).
     */
    public function create(string $name, ?int $teamId, ?array $overrides = null): ?string
    {
        return $this->keyStore?->createKey($name, $teamId, $overrides);
    }

    public function validateOverrides(?int $teamId, array $overrides): array
    {
        $team = null !== $teamId ? $this->keyStore?->findTeamById($teamId) : null;
        if (null === $team) {
            return [];
        }

        return $this->scopeValidator->validate($team->getRules() ?? [], $overrides);
    }


    /**
     * Template data for the key creation form (teams list, model aliases).
     * @return array{teams:list<Team>,key:null,model_aliases:string[],submitted:array}
     */
    public function createForm(): array
    {
        return [
            'teams' => $this->keyStore?->listTeams() ?? [],
            'key' => null,
            'model_aliases' => array_map(static fn (array $m): string => $m['alias'], $this->catalog?->listModels() ?? []),
            'submitted' => [],
            'action' => 'new',
        ];
    }


    /**
     * Full detail data for a key: budgets, logs, breakdowns.
     * @return array{key:ApiKey,usage_today:KeyUsage|null,usage_month:KeyUsage|null,key_stats:array,status_breakdown:array,daily_usage:array,...}|null
     */
    public function detail(int $id): ?array
    {
        $key = $this->keyStore?->findKeyById($id);
        if (null === $key) {
            return null;
        }

        $filters = ['keyId' => $id];

        return [
            'key' => $key,
            'usage_today' => $this->keyStore?->getKeyUsage($id, date('Y-m-d'), date('Y-m-d')),
            'usage_month' => $this->keyStore?->getKeyUsage($id, date('Y-m-01'), date('Y-m-t')),
            'key_stats' => $this->requestLogStore?->getKeyStats($id) ?? ['requests' => 0, 'tokens' => 0, 'cost' => 0, 'errors' => 0, 'avg_duration_ms' => 0],
            'status_breakdown' => $this->requestLogStore?->getStatusBreakdown($filters) ?? [],
            'daily_usage' => $this->requestLogStore?->getDailyUsage(60, $filters) ?? [],
            'provider_breakdown' => $this->requestLogStore?->getBreakdown('provider', $filters, 15) ?? [],
            'model_breakdown' => $this->requestLogStore?->getBreakdown('pick_alias', $filters, 15) ?? [],
            'team_breakdown' => $this->requestLogStore?->getBreakdown('team', $filters, 15) ?? [],
            'recent_logs' => $this->requestLogStore?->getRecentLogsForKey($id, 50) ?? [],
        ];
    }


    /**
     * Template data for the key edit form (pre-populated with current key values).
     * @return array{teams:list<Team>,key:ApiKey|null,model_aliases:string[],submitted:array,action:string}
     */
    public function editForm(int $id): array
    {
        $key = $this->keyStore?->findKeyById($id);
        $overrides = $key?->getOverrides() ?? [];

        return [
            'teams' => $this->keyStore?->listTeams() ?? [],
            'key' => $key,
            'model_aliases' => array_map(static fn (array $m): string => $m['alias'], $this->catalog?->listModels() ?? []),
            'submitted' => [
                'name' => $key?->getName() ?? '',
                'team_id' => (string) ($key?->getTeamId() ?? ''),
                'models' => isset($overrides['allowed_models']) ? implode(', ', $overrides['allowed_models']) : '',
                'budget_per_day' => (string) ($overrides['budget_per_day'] ?? ''),
                'budget_per_month' => (string) ($overrides['budget_per_month'] ?? ''),
                'rate_limit' => (string) ($overrides['rate_limit_per_minute'] ?? ''),
                'rate_limit_per_day' => (string) ($overrides['rate_limit_per_day'] ?? ''),
            ],
            'action' => 'edit',
        ];
    }

    /**
     * Update an existing key's name and overrides.
     */
    public function update(int $id, string $name, ?array $overrides = null): void
    {
        $key = $this->keyStore?->findKeyById($id);
        if (null === $key) {
            return;
        }

        $key->setName($name);
        $key->setOverrides($overrides);
        $this->keyStore?->saveKey($key);
    }

    /**
     * Regenerate a key token while keeping the same entity and history.
     * Returns the new raw token, or null if the key was not found.
     */
    public function regenerate(int $id): ?string
    {
        return $this->keyStore?->regenerateKey($id);
    }

    /** Disable (revoke) a key so it can no longer authenticate. */
    public function revoke(int $id): void
    {
        $key = $this->keyStore?->findKeyById($id);
        if (null === $key) {
            return;
        }

        $key->setEnabled(false);
        $this->keyStore?->saveKey($key);
    }

}
