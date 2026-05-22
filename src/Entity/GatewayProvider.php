<?php

declare(strict_types=1);

namespace AIGateway\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * Provider definition (format, endpoint, credentials).
 */
#[ORM\Entity]
#[ORM\Table(name: 'gateway_providers')]
#[ORM\HasLifecycleCallbacks]
class GatewayProvider
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 64)]
    private string $name;

    #[ORM\Column(type: 'string', length: 20)]
    private string $format;

    #[ORM\Column(type: 'string', length: 256)]
    private string $apiKey;

    #[ORM\Column(type: 'string', length: 256, nullable: true)]
    private ?string $baseUrl = null;

    #[ORM\Column(type: 'string', length: 128, options: ['default' => '/chat/completions'])]
    private string $completionsPath = '/chat/completions';

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\OneToMany(targetEntity: GatewayModel::class, mappedBy: 'provider', cascade: ['remove'])]
    private Collection $models;

    public function __construct()
    {
        $this->models = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getName(): string { return $this->name; }
    public function setName(string $name): void { $this->name = $name; }
    public function getFormat(): string { return $this->format; }
    public function setFormat(string $format): void { $this->format = $format; }
    public function getApiKey(): string { return $this->apiKey; }
    public function setApiKey(string $apiKey): void { $this->apiKey = $apiKey; }
    public function getBaseUrl(): ?string { return $this->baseUrl; }
    public function setBaseUrl(?string $baseUrl): void { $this->baseUrl = $baseUrl; }
    public function getCompletionsPath(): string { return $this->completionsPath; }
    public function setCompletionsPath(string $completionsPath): void { $this->completionsPath = $completionsPath; }
}
