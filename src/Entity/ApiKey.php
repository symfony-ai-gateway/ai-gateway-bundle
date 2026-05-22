<?php

declare(strict_types=1);

namespace AIGateway\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Gateway API key used to authenticate incoming requests.
 */
#[ORM\Entity]
#[ORM\Table(name: 'gateway_api_keys')]
#[ORM\HasLifecycleCallbacks]
class ApiKey
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 128)]
    private string $name;

    #[ORM\Column(type: 'string', length: 64, unique: true)]
    private string $tokenHash;

    #[ORM\Column(type: 'string', length: 16)]
    private string $tokenPrefix;

    #[ORM\ManyToOne(targetEntity: Team::class)]
    #[ORM\JoinColumn(name: 'team_id', referencedColumnName: 'id', nullable: true)]
    private ?Team $team = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $overrides = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $enabled = true;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }

    public function getTokenHash(): string { return $this->tokenHash; }
    public function setTokenHash(string $hash): self { $this->tokenHash = $hash; return $this; }

    public function getTokenPrefix(): string { return $this->tokenPrefix; }
    public function setTokenPrefix(string $prefix): self { $this->tokenPrefix = $prefix; return $this; }

    public function getTeam(): ?Team { return $this->team; }
    public function setTeam(?Team $team): self { $this->team = $team; return $this; }

    public function getTeamId(): ?int { return $this->team?->getId(); }

    public function getOverrides(): ?array { return $this->overrides; }
    public function setOverrides(?array $overrides): self { $this->overrides = $overrides; return $this; }

    public function isEnabled(): bool { return $this->enabled; }
    public function setEnabled(bool $enabled): self { $this->enabled = $enabled; return $this; }

    public function getExpiresAt(): ?\DateTimeImmutable { return $this->expiresAt; }
    public function setExpiresAt(?\DateTimeImmutable $expiresAt): self { $this->expiresAt = $expiresAt; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function isExpired(): bool
    {
        return null !== $this->expiresAt && $this->expiresAt < new \DateTimeImmutable();
    }
}
