<?php

declare(strict_types=1);

namespace AIGateway\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Tracks daily token and cost usage for an API key.
 */
#[ORM\Entity]
#[ORM\Table(name: 'gateway_api_key_usage')]
class ApiKeyUsage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\ManyToOne(targetEntity: ApiKey::class)]
    #[ORM\JoinColumn(name: 'key_id', referencedColumnName: 'id', nullable: false)]
    private ApiKey $key;

    #[ORM\Column(type: 'date')]
    private \DateTime $date;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $requests = 0;

    #[ORM\Column(type: 'integer')]
    private int $tokens = 0;

    #[ORM\Column(type: 'float')]
    private float $costUsd = 0.0;

    public function getId(): int { return $this->id; }
    public function getKey(): ApiKey { return $this->key; }
    public function setKey(ApiKey $key): void { $this->key = $key; }
    public function getDate(): \DateTimeInterface { return $this->date; }
    public function setDate(\DateTime $date): void { $this->date = $date; }
    public function getRequests(): int { return $this->requests; }
    public function setRequests(int $requests): void { $this->requests = $requests; }
    public function getTokens(): int { return $this->tokens; }
    public function setTokens(int $tokens): void { $this->tokens = $tokens; }
    public function getCostUsd(): float { return $this->costUsd; }
    public function setCostUsd(float $costUsd): void { $this->costUsd = $costUsd; }
}
