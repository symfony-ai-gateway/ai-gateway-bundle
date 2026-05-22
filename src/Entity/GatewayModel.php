<?php

declare(strict_types=1);

namespace AIGateway\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Model alias that maps a short name to a provider model.
 */
#[ORM\Entity]
#[ORM\Table(name: 'gateway_models')]
#[ORM\HasLifecycleCallbacks]
class GatewayModel
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(type: 'string', length: 64, unique: true)]
    private string $alias;

    #[ORM\ManyToOne(targetEntity: GatewayProvider::class)]
    #[ORM\JoinColumn(name: 'provider_name', referencedColumnName: 'name', nullable: false)]
    private GatewayProvider $provider;

    #[ORM\Column(type: 'string', length: 128)]
    private string $model;

    #[ORM\Column(type: 'float')]
    private float $pricingInput = 0.0;

    #[ORM\Column(type: 'float')]
    private float $pricingOutput = 0.0;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): int { return $this->id; }
    public function getAlias(): string { return $this->alias; }
    public function setAlias(string $alias): void { $this->alias = $alias; }
    public function getProvider(): GatewayProvider { return $this->provider; }
    public function setProvider(GatewayProvider $provider): void { $this->provider = $provider; }
    public function getModel(): string { return $this->model; }
    public function setModel(string $model): void { $this->model = $model; }
    public function getPricingInput(): float { return $this->pricingInput; }
    public function setPricingInput(float $pricingInput): void { $this->pricingInput = $pricingInput; }
    public function getPricingOutput(): float { return $this->pricingOutput; }
    public function setPricingOutput(float $pricingOutput): void { $this->pricingOutput = $pricingOutput; }
}
