<?php

declare(strict_types=1);

namespace AIGateway\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'gateway_teams')]
#[ORM\HasLifecycleCallbacks]

/** Team grouping several API keys with shared budgets. */
class Team
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 64, unique: true)]
    private string $name;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $rules = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getBudgetPerDay(): ?float { return $this->rules['budget_per_day'] ?? null; }
    public function getBudgetPerMonth(): ?float { return $this->rules['budget_per_month'] ?? null; }
    public function getRateLimitPerMinute(): ?int { return $this->rules['rate_limit_per_minute'] ?? null; }
    public function getAllowedModels(): ?array { return $this->rules['allowed_models'] ?? null; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }

    public function getRules(): ?array { return $this->rules; }
    public function setRules(?array $rules): self { $this->rules = $rules; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
