<?php

declare(strict_types=1);

namespace AIGateway\Auth\Store;

use AIGateway\Entity\ApiKey;
use AIGateway\Entity\Team;

/**
 * Persistence contract for API keys and teams.
 *
 * Implementations store and retrieve key hashes, team configurations,
 * and daily usage counters used for authentication and budget checks.
 */
interface KeyStoreInterface
{
    /** Look up one key by its SHA-256 hash, or null if not found. */
    public function findKeyByHash(string $hash): ?ApiKey;

    /** Look up one key by its integer id, or null if not found. */
    public function findKeyById(int $id): ?ApiKey;

    /** Return every key in the store. */
    public function listKeys(): array;

    /** Persist a key entity (create or update). */
    public function saveKey(ApiKey $key): void;

    /** Generate a new gateway key and return the raw token. */
    public function createKey(string $name, ?int $teamId = null, ?array $overrides = null): ?string;

    /**
     * Regenerate the token for an existing key entity.
     * Returns the new raw token, or null if the key was not found.
     */
    public function regenerateKey(int $id): ?string;

    /** Return every team. */
    public function listTeams(): array;

    /** Look up one team by its integer id, or null if not found. */
    public function findTeamById(int $id): ?Team;

    /** Persist a team entity (create or update). */
    public function saveTeam(Team $team): void;

    /**
     * Record token and cost usage for a key on a given date.
     * Creates a new daily usage row if none exists for that date.
     */
    public function incrementKeyUsage(int $keyId, string $date, int $tokens, float $costUsd): void;

    /**
     * Read aggregated token and cost usage for a key within a date range.
     */
    public function getKeyUsage(int $keyId, string $periodStart, string $periodEnd): KeyUsage;
}
