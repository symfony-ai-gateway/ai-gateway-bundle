<?php

declare(strict_types=1);

namespace AIGateway\Auth\Store;

use AIGateway\Entity\ApiKey;
use AIGateway\Entity\Team;
use AIGateway\Entity\ApiKeyUsage;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Doctrine ORM implementation of the key store.
 */
final class OrmKeyStore implements KeyStoreInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    public function findKeyByHash(string $keyHash): ?ApiKey
    {
        $entity = $this->em->getRepository(\AIGateway\Entity\ApiKey::class)
            ->findOneBy(['tokenHash' => $keyHash, 'enabled' => true]);

        return $entity;
    }

    public function findKeyById(int $id): ?ApiKey
    {
        $entity = $this->em->find(\AIGateway\Entity\ApiKey::class, $id);
        return $entity;
    }

    public function listKeys(): array
    {
        $entities = $this->em->getRepository(\AIGateway\Entity\ApiKey::class)
            ->findBy([], ['createdAt' => 'DESC']);

        return $entities;
    }

    public function createKey(string $name, ?int $teamId = null, ?array $overrides = null): string
    {
        $raw = 'aigw_' . bin2hex(random_bytes(24));
        $hash = hash('sha256', $raw);

        $entity = new \AIGateway\Entity\ApiKey();
        $entity->setName($name);
        $entity->setTokenHash($hash);
        $entity->setTokenPrefix(substr($raw, 0, 8));
        $entity->setOverrides($overrides);

        if (null !== $teamId) {
            $team = $this->em->find(\AIGateway\Entity\Team::class, $teamId);
            $entity->setTeam($team);
        }

        $this->em->persist($entity);
        $this->em->flush();

        return $raw;
    }

    public function regenerateKey(int $id): ?string
    {
        $entity = $this->em->find(\AIGateway\Entity\ApiKey::class, $id);
        if (null === $entity) {
            return null;
        }

        $raw = 'aigw_' . bin2hex(random_bytes(24));
        $hash = hash('sha256', $raw);

        $entity->setTokenHash($hash);
        $entity->setTokenPrefix(substr($raw, 0, 8));
        $this->em->flush();

        return $raw;
    }

    public function saveKey(ApiKey $apiKey): void
    {
        $entity = null !== $apiKey->getId()
            ? $this->em->find(\AIGateway\Entity\ApiKey::class, $apiKey->getId())
            : null;

        if (null === $entity) {
            $entity = new \AIGateway\Entity\ApiKey();
            $this->em->persist($entity);
        }

        $entity->setName($apiKey->getName());
        $entity->setTokenHash($apiKey->getTokenHash());
        $entity->setTokenPrefix($apiKey->getTokenPrefix());
        $entity->setEnabled($apiKey->isEnabled());
        $entity->setExpiresAt($apiKey->getExpiresAt());

        $entity->setTeam($apiKey->getTeam());
        $entity->setOverrides($apiKey->getOverrides());
        $this->em->flush();
    }

    public function deleteKey(int $id): void
    {
        $entity = $this->em->find(\AIGateway\Entity\ApiKey::class, $id);
        if (null !== $entity) {
            $this->em->remove($entity);
            $this->em->flush();
        }
    }

    public function listTeams(): array
    {
        $entities = $this->em->getRepository(\AIGateway\Entity\Team::class)
            ->findBy([], ['name' => 'ASC']);

        return $entities;
    }

    public function findTeamById(int $id): ?Team
    {
        return $this->em->find(\AIGateway\Entity\Team::class, $id);
    }

    public function saveTeam(Team $team): void
    {
        $entity = null !== $team->getId()
            ? $this->em->find(\AIGateway\Entity\Team::class, $team->getId())
            : null;

        if (null === $entity) {
            $entity = $team;
            $this->em->persist($entity);
        } else {
            $entity->setName($team->getName());
            $entity->setRules($team->getRules());
        }
        $this->em->flush();
    }

    public function deleteTeam(int $id): void
    {
        $entity = $this->em->find(\AIGateway\Entity\Team::class, $id);
        if (null !== $entity) {
            $this->em->remove($entity);
            $this->em->flush();
        }
    }

    public function incrementKeyUsage(int $keyId, string $date, int $tokens, float $costUsd): void
    {
        $key = $this->em->find(\AIGateway\Entity\ApiKey::class, $keyId);
        if (null === $key) {
            return;
        }

        $dateObj = new \DateTime($date);
        $usage = $this->em->getRepository(ApiKeyUsage::class)
            ->findOneBy(['key' => $key, 'date' => $dateObj]);

        if (null === $usage) {
            $usage = new ApiKeyUsage();
            $usage->setKey($key);
            $usage->setDate($dateObj);
            $this->em->persist($usage);
        }

        $usage->setRequests($usage->getRequests() + 1);
        $usage->setTokens($usage->getTokens() + $tokens);
        $usage->setCostUsd($usage->getCostUsd() + $costUsd);
        $this->em->flush();
    }
    public function getKeyUsage(int $keyId, string $periodStart, string $periodEnd): KeyUsage
    {
        $start = new \DateTime($periodStart);
        $end = new \DateTime($periodEnd);

        $qb = $this->em->createQueryBuilder();
        $result = $qb->select('COALESCE(SUM(u.requests), 0) as requests, COALESCE(SUM(u.tokens), 0) as tokens, COALESCE(SUM(u.costUsd), 0) as cost_usd')
            ->from(ApiKeyUsage::class, 'u')
            ->where('u.key = :keyId')
            ->andWhere('u.date >= :start')
            ->andWhere('u.date <= :end')
            ->setParameter('keyId', $keyId)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getOneOrNullResult();

        if (null === $result) {
            return new KeyUsage();
        }

        return new KeyUsage(
            requests: (int) $result['requests'],
            tokens: (int) $result['tokens'],
            costUsd: (float) $result['cost_usd'],
        );
    }
}
