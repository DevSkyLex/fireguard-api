<?php

declare(strict_types=1);

namespace Otp\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entity TotpEnrollmentRecord.
 *
 * Doctrine entity for TOTP enrollment persistence. One row per user.
 *
 * @category Entity
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'totp_enrollments')]
class TotpEnrollmentRecord
{
  // #region Properties
  #[ORM\Id]
  #[ORM\Column(name: 'user_id', type: 'string', length: 36)]
  private string $userId;

  #[ORM\Column(name: 'active_secret', type: 'string', length: 255, nullable: true)]
  private ?string $activeSecret = null;

  #[ORM\Column(name: 'active_confirmed_at', type: 'datetime_immutable', nullable: true)]
  private ?DateTimeImmutable $activeConfirmedAt = null;

  #[ORM\Column(name: 'pending_secret', type: 'string', length: 255, nullable: true)]
  private ?string $pendingSecret = null;

  #[ORM\Column(name: 'pending_created_at', type: 'datetime_immutable', nullable: true)]
  private ?DateTimeImmutable $pendingCreatedAt = null;

  #[ORM\Column(type: 'integer')]
  private int $attempts = 0;

  #[ORM\Column(name: 'max_attempts', type: 'integer')]
  private int $maxAttempts;

  #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
  private DateTimeImmutable $createdAt;

  #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
  private DateTimeImmutable $updatedAt;
  // #endregion

  // #region Getters
  public function getUserId(): string
  {
    return $this->userId;
  }

  public function getActiveSecret(): ?string
  {
    return $this->activeSecret;
  }

  public function getActiveConfirmedAt(): ?DateTimeImmutable
  {
    return $this->activeConfirmedAt;
  }

  public function getPendingSecret(): ?string
  {
    return $this->pendingSecret;
  }

  public function getPendingCreatedAt(): ?DateTimeImmutable
  {
    return $this->pendingCreatedAt;
  }

  public function getAttempts(): int
  {
    return $this->attempts;
  }

  public function getMaxAttempts(): int
  {
    return $this->maxAttempts;
  }

  public function getCreatedAt(): DateTimeImmutable
  {
    return $this->createdAt;
  }

  public function getUpdatedAt(): DateTimeImmutable
  {
    return $this->updatedAt;
  }
  // #endregion

  // #region Setters
  public function setUserId(string $userId): self
  {
    $this->userId = $userId;

    return $this;
  }

  public function setActiveSecret(?string $activeSecret): self
  {
    $this->activeSecret = $activeSecret;

    return $this;
  }

  public function setActiveConfirmedAt(?DateTimeImmutable $activeConfirmedAt): self
  {
    $this->activeConfirmedAt = $activeConfirmedAt;

    return $this;
  }

  public function setPendingSecret(?string $pendingSecret): self
  {
    $this->pendingSecret = $pendingSecret;

    return $this;
  }

  public function setPendingCreatedAt(?DateTimeImmutable $pendingCreatedAt): self
  {
    $this->pendingCreatedAt = $pendingCreatedAt;

    return $this;
  }

  public function setAttempts(int $attempts): self
  {
    $this->attempts = $attempts;

    return $this;
  }

  public function setMaxAttempts(int $maxAttempts): self
  {
    $this->maxAttempts = $maxAttempts;

    return $this;
  }

  public function setCreatedAt(DateTimeImmutable $createdAt): self
  {
    $this->createdAt = $createdAt;

    return $this;
  }

  public function setUpdatedAt(DateTimeImmutable $updatedAt): self
  {
    $this->updatedAt = $updatedAt;

    return $this;
  }
  // #endregion
}
