<?php

declare(strict_types=1);

namespace TrustedDevice\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entity TrustedDeviceRecord
 */
#[ORM\Entity]
#[ORM\Table(name: 'trusted_devices')]
#[ORM\Index(name: 'idx_td_user', columns: ['user_id'])]
#[ORM\Index(name: 'idx_td_token', columns: ['token_hash'])]
#[ORM\UniqueConstraint(name: 'uniq_td_user_fingerprint', columns: ['user_id', 'fingerprint'])]
class TrustedDeviceRecord
{
  #[ORM\Id]
  #[ORM\Column(type: 'guid')]
  private string $id;

  #[ORM\Column(name: 'user_id', type: 'string', length: 36)]
  private string $userId;

  #[ORM\Column(name: 'token_hash', type: 'string', length: 255)]
  private string $tokenHash;

  #[ORM\Column(type: 'string', length: 255)]
  private string $fingerprint;

  #[ORM\Column(name: 'user_agent', type: 'string', length: 500)]
  private string $userAgent;

  #[ORM\Column(name: 'ip_address', type: 'string', length: 45, nullable: true)]
  private ?string $ipAddress = null;

  #[ORM\Column(type: 'string', length: 255)]
  private string $name;

  #[ORM\Column(name: 'last_used_at', type: 'datetime_immutable')]
  private DateTimeImmutable $lastUsedAt;

  #[ORM\Column(name: 'expires_at', type: 'datetime_immutable')]
  private DateTimeImmutable $expiresAt;

  #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
  private DateTimeImmutable $createdAt;

  #[ORM\Column(type: 'boolean')]
  private bool $revoked = false;

  // Getters
  public function getId(): string
  {
    return $this->id;
  }
  public function getUserId(): string
  {
    return $this->userId;
  }
  public function getTokenHash(): string
  {
    return $this->tokenHash;
  }
  public function getFingerprint(): string
  {
    return $this->fingerprint;
  }
  public function getUserAgent(): string
  {
    return $this->userAgent;
  }
  public function getIpAddress(): ?string
  {
    return $this->ipAddress;
  }
  public function getName(): string
  {
    return $this->name;
  }
  public function getLastUsedAt(): DateTimeImmutable
  {
    return $this->lastUsedAt;
  }
  public function getExpiresAt(): DateTimeImmutable
  {
    return $this->expiresAt;
  }
  public function getCreatedAt(): DateTimeImmutable
  {
    return $this->createdAt;
  }
  public function isRevoked(): bool
  {
    return $this->revoked;
  }

  // Setters
  public function setId(string $id): self
  {
    $this->id = $id;
    return $this;
  }
  public function setUserId(string $userId): self
  {
    $this->userId = $userId;
    return $this;
  }
  public function setTokenHash(string $tokenHash): self
  {
    $this->tokenHash = $tokenHash;
    return $this;
  }
  public function setFingerprint(string $fingerprint): self
  {
    $this->fingerprint = $fingerprint;
    return $this;
  }
  public function setUserAgent(string $userAgent): self
  {
    $this->userAgent = $userAgent;
    return $this;
  }
  public function setIpAddress(?string $ipAddress): self
  {
    $this->ipAddress = $ipAddress;
    return $this;
  }
  public function setName(string $name): self
  {
    $this->name = $name;
    return $this;
  }
  public function setLastUsedAt(DateTimeImmutable $lastUsedAt): self
  {
    $this->lastUsedAt = $lastUsedAt;
    return $this;
  }
  public function setExpiresAt(DateTimeImmutable $expiresAt): self
  {
    $this->expiresAt = $expiresAt;
    return $this;
  }
  public function setCreatedAt(DateTimeImmutable $createdAt): self
  {
    $this->createdAt = $createdAt;
    return $this;
  }
  public function setRevoked(bool $revoked): self
  {
    $this->revoked = $revoked;
    return $this;
  }
}
