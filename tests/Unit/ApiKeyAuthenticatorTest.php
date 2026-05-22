<?php

declare(strict_types=1);

namespace AIGateway\Tests\Unit;

use AIGateway\Auth\ApiKeyAuthenticator;
use AIGateway\Auth\Store\KeyStoreInterface;
use AIGateway\Auth\Store\KeyUsage;
use AIGateway\Entity\ApiKey;
use AIGateway\Entity\Team;
use PHPUnit\Framework\TestCase;

final class ApiKeyAuthenticatorTest extends TestCase
{
    public function test_team_rules_are_used_when_key_overrides_are_empty(): void
    {
        $team = $this->createTeam([
            'budget_per_day' => 10.5,
            'rate_limit_per_day' => 42,
        ]);
        $apiKey = $this->createApiKey($team, []);

        $authenticator = new ApiKeyAuthenticator(new InMemoryKeyStore($apiKey));
        $context = $authenticator->authenticate('test-token');

        self::assertSame(10.5, $context->resolvedRules->budgetPerDay);
        self::assertSame(42, $context->resolvedRules->rateLimitPerDay);
    }

    public function test_key_rules_can_only_restrict_team_rules(): void
    {
        $team = $this->createTeam([
            'budget_per_day' => 10.5,
            'budget_per_month' => 100.0,
            'rate_limit_per_minute' => 60,
            'rate_limit_per_day' => 42,
            'allowed_models' => ['glm', 'gpt-4', 'claude'],
            'blocked_models' => ['gpt-4'],
        ]);
        $apiKey = $this->createApiKey($team, [
            'budget_per_day' => 20.0,
            'budget_per_month' => 50.0,
            'rate_limit_per_minute' => 120,
            'rate_limit_per_day' => 10,
            'allowed_models' => ['glm', 'mistral'],
            'blocked_models' => ['claude'],
        ]);

        $authenticator = new ApiKeyAuthenticator(new InMemoryKeyStore($apiKey));
        $context = $authenticator->authenticate('test-token');

        self::assertSame(10.5, $context->resolvedRules->budgetPerDay);
        self::assertSame(50.0, $context->resolvedRules->budgetPerMonth);
        self::assertSame(60, $context->resolvedRules->rateLimitPerMinute);
        self::assertSame(10, $context->resolvedRules->rateLimitPerDay);
        self::assertSame(['glm'], $context->resolvedRules->allowedModels);
        self::assertSame(['gpt-4', 'claude'], $context->resolvedRules->blockedModels);
    }

    public function test_rate_limit_per_day_is_preserved(): void
    {
        $team = $this->createTeam([
            'rate_limit_per_day' => 99,
            'budget_per_day' => 1.0,
        ]);
        $apiKey = $this->createApiKey($team, [
            'budget_per_month' => 20.0,
        ]);

        $authenticator = new ApiKeyAuthenticator(new InMemoryKeyStore($apiKey));
        $context = $authenticator->authenticate('test-token');

        self::assertSame(99, $context->resolvedRules->rateLimitPerDay);
    }

    private function createTeam(array $rules): Team
    {
        $team = new Team();
        $team->setName('team-a');
        $team->setRules($rules);

        return $team;
    }

    private function createApiKey(Team $team, array $overrides): ApiKey
    {
        $apiKey = new ApiKey();
        $apiKey->setName('key-a');
        $apiKey->setTokenHash(hash('sha256', 'test-token'));
        $apiKey->setTokenPrefix('aigw_test');
        $apiKey->setTeam($team);
        $apiKey->setOverrides($overrides);

        return $apiKey;
    }
}

final class InMemoryKeyStore implements KeyStoreInterface
{
    public function __construct(private readonly ApiKey $apiKey) {}

    public function findKeyByHash(string $hash): ?ApiKey
    {
        return $this->apiKey->getTokenHash() === $hash ? $this->apiKey : null;
    }

    public function findKeyById(int $id): ?ApiKey
    {
        return null;
    }

    public function listKeys(): array
    {
        return [$this->apiKey];
    }

    public function saveKey(ApiKey $key): void
    {
    }

    public function createKey(string $name, ?int $teamId = null, ?array $overrides = null): ?string
    {
        return null;
    }

    public function listTeams(): array
    {
        return [];
    }

    public function findTeamById(int $id): ?Team
    {
        return null;
    }

    public function saveTeam(Team $team): void
    {
    }

    public function incrementKeyUsage(int $keyId, string $date, int $tokens, float $costUsd): void
    {
    }

    public function getKeyUsage(int $keyId, string $periodStart, string $periodEnd): KeyUsage
    {
        throw new \LogicException('Not implemented for test double.');
    }
}
