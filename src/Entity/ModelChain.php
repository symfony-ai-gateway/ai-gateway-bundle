<?php

declare(strict_types=1);

namespace AIGateway\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * Named chain of model aliases with priority and weight for load balancing.
 */
#[ORM\Entity]
#[ORM\Table(name: 'gateway_model_chains')]
#[ORM\HasLifecycleCallbacks]
class ModelChain
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(type: 'string', length: 64, unique: true)]
    private string $alias;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\OneToMany(targetEntity: ModelChainStep::class, mappedBy: 'chain', cascade: ['remove'])]
    private Collection $steps;

    public function __construct()
    {
        $this->steps = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): int { return $this->id; }
    public function getAlias(): string { return $this->alias; }
    public function setAlias(string $alias): void { $this->alias = $alias; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getSteps(): Collection { return $this->steps; }
}
