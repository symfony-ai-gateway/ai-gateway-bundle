<?php

declare(strict_types=1);

namespace AIGateway\Logging;

use AIGateway\Core\GatewayResponse;
use AIGateway\Entity\RequestLog;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Persistence layer for request logs and aggregated statistics.
 */
final class RequestLogStore
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    public function logResponse(
        GatewayResponse $response,
        string $modelAlias,
        float $durationMs,
        ?int $keyId = null,
        ?string $keyName = null,
        ?int $teamId = null,
        ?string $queryModel = null,
        ?string $resolvedModel = null,
        ?string $pickAlias = null,
    ): void {
        $log = new RequestLog();
        $log->setModelAlias($modelAlias);
        $log->setPickAlias($pickAlias);
        $log->setQueryModel($queryModel);
        $log->setResolvedModel($resolvedModel ?? $response->model);
        $log->setProviderName($response->provider);
        $log->setStatusCode($response->statusCode);
        $log->setDurationMs((int) $durationMs);
        $log->setPromptTokens($response->usage->promptTokens ?? 0);
        $log->setCompletionTokens($response->usage->completionTokens ?? 0);
        $log->setCostUsd($response->costUsd);
        $log->setKeyName($keyName);
        $log->setKeyId($keyId);
        $log->setTeamId($teamId);

        $this->em->persist($log);
        $this->em->flush();
    }

    public function logBlockedRequest(
        string $modelAlias,
        string $provider,
        int $statusCode,
        string $error,
        ?int $keyId = null,
        ?string $keyName = null,
        ?int $teamId = null,
    ): void {
        $log = new RequestLog();
        $log->setModelAlias($modelAlias);
        $log->setResolvedModel(null);
        $log->setProviderName($provider);
        $log->setStatusCode($statusCode);
        $log->setError($error);
        $log->setKeyName($keyName);
        $log->setKeyId($keyId);
        $log->setTeamId($teamId);

        $this->em->persist($log);
        $this->em->flush();
    }

    public function getRecentLogs(int $limit = 100): array
    {
        return $this->em->getRepository(RequestLog::class)
            ->findBy([], ['createdAt' => 'DESC'], $limit);
    }

    public function getRecentLogRows(int $limit = 100, array $filters = []): array
    {
        $sql = 'SELECT l.id, l.model_alias, l.pick_alias, l.query_model, COALESCE(l.pick_alias, l.model_alias) AS resolved_model, l.provider_name, l.status_code, l.duration_ms, l.prompt_tokens, l.completion_tokens, (l.prompt_tokens + l.completion_tokens) AS total_tokens, l.cost_usd, l.key_name, l.key_id, l.team_id, t.name AS team_name, l.error, l.created_at FROM gateway_request_logs l LEFT JOIN gateway_teams t ON t.id = l.team_id';
        [$where, $params, $types] = $this->buildFilterClause($filters, 'l.');
        $sql .= $where . ' ORDER BY l.created_at DESC LIMIT :limit';
        $params['limit'] = $limit;
        $types['limit'] = ParameterType::INTEGER;

        try {
            return $this->conn()->executeQuery($sql, $params, $types)->fetchAllAssociative();
        } catch (\Throwable) {
            return [];
        }
    }

    public function getStats(array $filters = []): array
    {
        $sql = 'SELECT COUNT(*) AS total_requests, COALESCE(SUM(prompt_tokens), 0) AS total_prompt_tokens, COALESCE(SUM(completion_tokens), 0) AS total_completion_tokens, COALESCE(SUM(cost_usd), 0) AS total_cost, COALESCE(AVG(duration_ms), 0) AS avg_duration_ms, COALESCE(SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END), 0) AS total_errors FROM gateway_request_logs';
        [$where, $params, $types] = $this->buildFilterClause($filters);
        try {
            $row = $this->conn()->executeQuery($sql . $where, $params, $types)->fetchAssociative() ?: [];
        } catch (\Throwable) {
            $row = [];
        }

        return [
            'total_requests' => (int) ($row['total_requests'] ?? 0),
            'total_prompt_tokens' => (int) ($row['total_prompt_tokens'] ?? 0),
            'total_completion_tokens' => (int) ($row['total_completion_tokens'] ?? 0),
            'total_cost' => (float) ($row['total_cost'] ?? 0.0),
            'avg_duration_ms' => (float) ($row['avg_duration_ms'] ?? 0.0),
            'total_errors' => (int) ($row['total_errors'] ?? 0),
        ];
    }

    public function getDailyUsage(int $days = 30, array $filters = []): array
    {
        $sql = 'SELECT DATE(created_at) AS date, COUNT(*) AS requests, COALESCE(SUM(prompt_tokens), 0) AS prompt_tokens, COALESCE(SUM(completion_tokens), 0) AS completion_tokens, COALESCE(SUM(prompt_tokens + completion_tokens), 0) AS total_tokens, COALESCE(SUM(cost_usd), 0) AS cost, COALESCE(SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END), 0) AS errors FROM gateway_request_logs';
        $filters['fromDate'] = $filters['fromDate'] ?? (new \DateTimeImmutable(sprintf('-%d days', max(0, $days - 1))))->format('Y-m-d 00:00:00');
        [$where, $params, $types] = $this->buildFilterClause($filters);
        $sql .= $where . ' GROUP BY DATE(created_at) ORDER BY DATE(created_at) ASC';

        try {
            return $this->conn()->executeQuery($sql, $params, $types)->fetchAllAssociative();
        } catch (\Throwable) {
            return [];
        }
    }

    public function getBreakdown(string $dimension, array $filters = [], int $limit = 10): array
    {
        $select = match ($dimension) {
            'provider' => 'provider_name AS label, provider_name AS provider_name',
            'model' => 'model_alias AS label, model_alias AS model_alias',
            'pick_alias' => 'COALESCE(pick_alias, model_alias) AS label, pick_alias AS pick_alias',
            'query_model' => 'COALESCE(query_model, model_alias) AS label, query_model AS query_model',
            'resolved_model' => 'COALESCE(resolved_model, query_model, model_alias) AS label, resolved_model AS resolved_model',
            'key' => 'COALESCE(key_name, \'anonymous\') AS label, key_id AS key_id, key_name AS key_name',
            'team' => 'COALESCE(t.name, \'No team\') AS label, l.team_id AS team_id, t.name AS team_name',
            default => throw new \InvalidArgumentException(sprintf('Unsupported breakdown dimension "%s".', $dimension)),
        };
        $groupBy = match ($dimension) {
            'provider' => 'provider_name',
            'model' => 'model_alias',
            'pick_alias' => 'pick_alias',
            'query_model' => 'query_model',
            'resolved_model' => 'resolved_model',
            'key' => 'key_id, key_name',
            'team' => 'l.team_id, t.name',
        };

        $sql = 'SELECT ' . $select . ', COUNT(*) AS requests, COALESCE(SUM(prompt_tokens), 0) AS prompt_tokens, COALESCE(SUM(completion_tokens), 0) AS completion_tokens, COALESCE(SUM(prompt_tokens + completion_tokens), 0) AS tokens, COALESCE(SUM(cost_usd), 0) AS cost, COALESCE(SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END), 0) AS errors, COALESCE(AVG(duration_ms), 0) AS avg_duration_ms FROM gateway_request_logs l';
        if ('team' === $dimension) {
            $sql .= ' LEFT JOIN gateway_teams t ON t.id = l.team_id';
        }

        [$where, $params, $types] = $this->buildFilterClause($filters, 'l.');
        $sql .= $where . ' GROUP BY ' . $groupBy . ' ORDER BY cost DESC, requests DESC LIMIT :limit';
        $params['limit'] = $limit;
        $types['limit'] = ParameterType::INTEGER;

        try {
            return $this->conn()->executeQuery($sql, $params, $types)->fetchAllAssociative();
        } catch (\Throwable) {
            return [];
        }
    }

    public function getStatusBreakdown(array $filters = []): array
    {
        $sql = 'SELECT CASE WHEN status_code >= 500 THEN \'5xx\' WHEN status_code >= 400 THEN \'4xx\' WHEN status_code >= 300 THEN \'3xx\' ELSE \'2xx\' END AS family, COUNT(*) AS requests FROM gateway_request_logs';
        [$where, $params, $types] = $this->buildFilterClause($filters);
        $sql .= $where . ' GROUP BY family ORDER BY family ASC';
        try {
            return $this->conn()->executeQuery($sql, $params, $types)->fetchAllAssociative();
        } catch (\Throwable) {
            return [];
        }
    }

    public function getProviderStats(string $providerName): array
    {
        return $this->getStats(['provider' => $providerName]);
    }

    public function getModelStats(string $modelAlias): array
    {
        return $this->getStats(['pickAlias' => $modelAlias]);
    }

    public function getChainStats(string $chainAlias): array
    {
        return $this->getStats(['model' => $chainAlias]);
    }

    public function getKeyStats(int $keyId): array
    {
        $stats = $this->getStats(['keyId' => $keyId]);
        return [
            'requests' => $stats['total_requests'],
            'tokens' => $stats['total_prompt_tokens'] + $stats['total_completion_tokens'],
            'cost' => $stats['total_cost'],
            'errors' => $stats['total_errors'],
            'avg_duration_ms' => $stats['avg_duration_ms'],
        ];
    }

    public function getTeamStats(int $teamId): array
    {
        $stats = $this->getStats(['teamId' => $teamId]);
        return [
            'requests' => $stats['total_requests'],
            'tokens' => $stats['total_prompt_tokens'] + $stats['total_completion_tokens'],
            'cost' => $stats['total_cost'],
            'errors' => $stats['total_errors'],
            'avg_duration_ms' => $stats['avg_duration_ms'],
        ];
    }

    public function getRecentLogsForModel(string $modelAlias, int $limit = 20): array
    {
        return $this->getRecentLogRows($limit, ['pickAlias' => $modelAlias]);
    }

    public function getRecentLogsForKey(int $keyId, int $limit = 20): array
    {
        return $this->getRecentLogRows($limit, ['keyId' => $keyId]);
    }

    public function getRecentLogsForProvider(string $providerName, int $limit = 20): array
    {
        return $this->getRecentLogRows($limit, ['provider' => $providerName]);
    }

    public function getRecentLogsForTeam(int $teamId, int $limit = 20): array
    {
        return $this->getRecentLogRows($limit, ['teamId' => $teamId]);
    }

    private function conn(): Connection
    {
        return $this->em->getConnection();
    }

    private function buildFilterClause(array $filters, string $prefix = ''): array
    {
        $where = [];
        $params = [];
        $types = [];

        if (!empty($filters['provider'])) {
            $where[] = $prefix . 'provider_name = :provider';
            $params['provider'] = $filters['provider'];
        }
        if (!empty($filters['model'])) {
            $where[] = $prefix . 'model_alias = :model';
            $params['model'] = $filters['model'];
        }
        if (!empty($filters['pickAlias'])) {
            $where[] = $prefix . 'pick_alias = :pickAlias';
            $params['pickAlias'] = $filters['pickAlias'];
        }
        if (!empty($filters['keyId'])) {
            $where[] = $prefix . 'key_id = :keyId';
            $params['keyId'] = (int) $filters['keyId'];
            $types['keyId'] = ParameterType::INTEGER;
        }
        if (!empty($filters['teamId'])) {
            $where[] = $prefix . 'team_id = :teamId';
            $params['teamId'] = (int) $filters['teamId'];
            $types['teamId'] = ParameterType::INTEGER;
        }
        if (!empty($filters['statusFamily'])) {
            if ('2xx' === $filters['statusFamily']) {
                $where[] = $prefix . 'status_code >= 200 AND ' . $prefix . 'status_code < 300';
            } elseif ('4xx' === $filters['statusFamily']) {
                $where[] = $prefix . 'status_code >= 400 AND ' . $prefix . 'status_code < 500';
            } elseif ('5xx' === $filters['statusFamily']) {
                $where[] = $prefix . 'status_code >= 500';
            }
        }
        if (!empty($filters['fromDate'])) {
            $where[] = $prefix . 'created_at >= :fromDate';
            $params['fromDate'] = $filters['fromDate'];
        }
        if (!empty($filters['toDate'])) {
            $where[] = $prefix . 'created_at <= :toDate';
            $params['toDate'] = $filters['toDate'];
        }
        if (!empty($filters['providers'])) {
            $where[] = $prefix . 'provider_name IN (:providers)';
            $params['providers'] = $filters['providers'];
            $types['providers'] = ArrayParameterType::STRING;
        }
        if (!empty($filters['models'])) {
            $where[] = $prefix . 'model_alias IN (:models)';
            $params['models'] = $filters['models'];
            $types['models'] = ArrayParameterType::STRING;
        }

        return [
            [] !== $where ? ' WHERE ' . implode(' AND ', $where) : '',
            $params,
            $types,
        ];
    }
}
