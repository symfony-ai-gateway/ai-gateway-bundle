<?php

declare(strict_types=1);

namespace AIGateway\Service;

use AIGateway\Auth\Store\KeyStoreInterface;
use AIGateway\Catalog\GatewayCatalog;
use AIGateway\Entity\ApiKey;
use AIGateway\Entity\Team;
use AIGateway\Logging\RequestLogStore;

/**
 * Encapsulates dashboard operations around teams.
 */
final class TeamAdminService
{
    public function __construct(
        private readonly ?KeyStoreInterface $keyStore = null,
        private readonly ?GatewayCatalog $catalog = null,
        private readonly ?RequestLogStore $requestLogStore = null,
    ) {}


    /**
     * Return every team with key counts and usage statistics.
     * @return array{teams:list<Team>,team_key_counts:array<int,int>,team_stats_map:array<int,array>}
     */
    /** Execute the operation. */
    public function list(): array
    {
        $teams = $this->keyStore?->listTeams() ?? [];
        $stats = [];
        $counts = [];

        foreach ($teams as $team) {
            if (null !== $team->getId()) {
                $stats[$team->getId()] = $this->requestLogStore?->getTeamStats($team->getId()) ?? ['requests' => 0, 'tokens' => 0, 'cost' => 0, 'errors' => 0, 'avg_duration_ms' => 0];
            }
        }

        foreach (($this->keyStore?->listKeys() ?? []) as $key) {
            if (null !== $key->getTeamId()) {
                $counts[$key->getTeamId()] = ($counts[$key->getTeamId()] ?? 0) + 1;
            }
        }

        return ['teams' => $teams, 'team_key_counts' => $counts, 'team_stats_map' => $stats];
    }


    /**
     * Create a new team with the given rules.
     */
    /** Execute the operation. */
    public function create(string $name, array $rules): void
    {
        $team = new Team();
        $team->setName($name);
        $team->setRules($rules);
        $this->keyStore?->saveTeam($team);
    }


    /**
     * Template data for the team create/edit form.
     * @return array{team:Team|null,teams:array,action:string,model_aliases:string[]}
     */
    /** Execute the operation. */
    public function formData(?Team $team, string $action): array
    {
        return [
            'team' => $team,
            'teams' => [],
            'action' => $action,
            'model_aliases' => array_map(static fn (array $m): string => $m['alias'], $this->catalog?->listModels() ?? []),
        ];
    }


    /** Find one team by id. */
    public function find(int $id): ?Team
    {
        return $this->keyStore?->findTeamById($id);
    }


    /** Update a team's name and rules. */
    public function update(Team $team, string $name, array $rules): void
    {
        $team->setName($name);
        $team->setRules($rules);
        $this->keyStore?->saveTeam($team);
    }


    /**
     * Full detail data for a team: keys, stats, breakdowns, recent logs.
     * @return array{team:Team,keys:list<ApiKey>,team_stats:array,...}|null
     */
    /** Execute the operation. */
    public function detail(int $id): ?array
    {
        $team = $this->keyStore?->findTeamById($id);
        if (null === $team) {
            return null;
        }

        $filters = ['teamId' => $id];

        return [
            'team' => $team,
            'keys' => array_values(array_filter($this->keyStore?->listKeys() ?? [], static fn (ApiKey $key): bool => $key->getTeamId() === $id)),
            'team_stats' => $this->requestLogStore?->getTeamStats($id) ?? ['requests' => 0, 'tokens' => 0, 'cost' => 0, 'errors' => 0, 'avg_duration_ms' => 0],
            'status_breakdown' => $this->requestLogStore?->getStatusBreakdown($filters) ?? [],
            'daily_usage' => $this->requestLogStore?->getDailyUsage(60, $filters) ?? [],
            'provider_breakdown' => $this->requestLogStore?->getBreakdown('provider', $filters, 15) ?? [],
            'model_breakdown' => $this->requestLogStore?->getBreakdown('pick_alias', $filters, 15) ?? [],
            'key_breakdown' => $this->requestLogStore?->getBreakdown('key', $filters, 15) ?? [],
            'recent_logs' => $this->requestLogStore?->getRecentLogsForTeam($id, 50) ?? [],
        ];
    }
}
