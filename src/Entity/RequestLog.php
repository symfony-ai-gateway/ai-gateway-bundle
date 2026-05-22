<?php

declare(strict_types=1);

namespace AIGateway\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Logged request: model alias, provider, tokens, cost, duration, status, error.
 */
#[ORM\Entity]
#[ORM\Table(name: 'gateway_request_logs')]
#[ORM\Index(name: 'idx_log_created', columns: ['created_at'])]
#[ORM\Index(name: 'idx_log_model', columns: ['model_alias'])]
#[ORM\Index(name: 'idx_log_key', columns: ['key_id'])]
class RequestLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(type: 'string', length: 64)]
    private string $modelAlias;

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $pickAlias = null;

    #[ORM\Column(type: 'string', length: 128, nullable: true)]
    private ?string $queryModel = null;

    #[ORM\Column(type: 'string', length: 128, nullable: true)]
    private ?string $resolvedModel = null;

    #[ORM\Column(type: 'string', length: 64)]
    private string $providerName;

    #[ORM\Column(type: 'integer')]
    private int $statusCode;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $durationMs = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $promptTokens = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $completionTokens = 0;

    #[ORM\Column(type: 'float', options: ['default' => 0.0])]
    private float $costUsd = 0.0;

    #[ORM\Column(type: 'string', length: 128, nullable: true)]
    private ?string $keyName = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $keyId = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $teamId = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $error = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): int { return $this->id; }

    public function getModelAlias(): string { return $this->modelAlias; }
    public function setModelAlias(string $v): self { $this->modelAlias = $v; return $this; }

    public function getQueryModel(): ?string { return $this->queryModel; }
    public function setQueryModel(?string $queryModel): void { $this->queryModel = $queryModel; }
    public function getPickAlias(): ?string { return $this->pickAlias; }
    public function setPickAlias(?string $pickAlias): void { $this->pickAlias = $pickAlias; }
    public function getResolvedModel(): ?string { return $this->resolvedModel; }
    public function setResolvedModel(?string $resolvedModel): void { $this->resolvedModel = $resolvedModel; }
    public function getProviderName(): string { return $this->providerName; }
    public function setProviderName(string $v): self { $this->providerName = $v; return $this; }

    public function getStatusCode(): int { return $this->statusCode; }
    public function setStatusCode(int $v): self { $this->statusCode = $v; return $this; }

    public function getDurationMs(): ?int { return $this->durationMs; }
    public function setDurationMs(?int $v): self { $this->durationMs = $v; return $this; }

    public function getPromptTokens(): int { return $this->promptTokens; }
    public function setPromptTokens(int $v): self { $this->promptTokens = $v; return $this; }

    public function getCompletionTokens(): int { return $this->completionTokens; }
    public function setCompletionTokens(int $v): self { $this->completionTokens = $v; return $this; }

    public function getCostUsd(): float { return $this->costUsd; }
    public function setCostUsd(float $v): self { $this->costUsd = $v; return $this; }

    public function getKeyName(): ?string { return $this->keyName; }
    public function setKeyName(?string $v): self { $this->keyName = $v; return $this; }

    public function getKeyId(): ?int { return $this->keyId; }
    public function setKeyId(?int $v): self { $this->keyId = $v; return $this; }

    public function getTeamId(): ?int { return $this->teamId; }
    public function setTeamId(?int $v): self { $this->teamId = $v; return $this; }

    public function getError(): ?string { return $this->error; }
    public function setError(?string $v): self { $this->error = $v; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
