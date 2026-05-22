<?php

declare(strict_types=1);

namespace AIGateway\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'gateway_model_chain_steps')]
#[ORM\HasLifecycleCallbacks]

/** One step inside a model chain. */
class ModelChainStep
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\ManyToOne(targetEntity: ModelChain::class, inversedBy: 'steps')]
    #[ORM\JoinColumn(name: 'chain_id', referencedColumnName: 'id', nullable: false)]
    private ModelChain $chain;

    #[ORM\ManyToOne(targetEntity: GatewayModel::class)]
    #[ORM\JoinColumn(name: 'model_id', referencedColumnName: 'id', nullable: false)]
    private GatewayModel $model;

    #[ORM\Column(type: 'integer')]
    private int $priority = 1;

    #[ORM\Column(type: 'integer')]
    private int $weight = 100;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): int { return $this->id; }

    public function getChain(): ModelChain { return $this->chain; }
    public function setChain(?ModelChain $chain): self { $this->chain = $chain; return $this; }

    public function getModel(): GatewayModel { return $this->model; }
    public function setModel(GatewayModel $model): self { $this->model = $model; return $this; }

    public function getPriority(): int { return $this->priority; }
    public function setPriority(int $priority): self { $this->priority = $priority; return $this; }

    public function getWeight(): int { return $this->weight; }
    public function setWeight(int $weight): self { $this->weight = $weight; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
