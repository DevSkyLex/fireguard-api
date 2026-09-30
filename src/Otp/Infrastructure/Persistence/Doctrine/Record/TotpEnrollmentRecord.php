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
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'totp_enrollments')]
class TotpEnrollmentRecord
{
  // #region Properties
  /**
   * Property activeSecretCiphertext
   *
   * Encrypted active TOTP secret; the mapper binds decryption to this user and the active slot.
   */
  #[ORM\Column(name: 'active_secret_ciphertext', type: 'text', nullable: true)]
  public ?string $activeSecretCiphertext = null;

  /**
   * Property pendingSecretCiphertext
   *
   * Encrypted pending TOTP secret; the mapper binds decryption to this user and the pending slot.
   */
  #[ORM\Column(name: 'pending_secret_ciphertext', type: 'text', nullable: true)]
  public ?string $pendingSecretCiphertext = null;

  /**
   * Property secretsEncrypted
   *
   * Whether secret slots use the versioned ciphertext columns.
   */
  #[ORM\Column(name: 'secrets_encrypted', type: 'boolean', options: ['default' => false])]
  public bool $secretsEncrypted = false;

  /**
   * Property userId
   *
   * Identifier of the user who owns this challenge.
   */
  #[ORM\Id]
  #[ORM\Column(name: 'user_id', type: 'string', length: 36)]
  private string $userId;

  /**
   * Property activeSecret
   *
   * Legacy plaintext active secret retained for migration compatibility.
   */
  #[ORM\Column(name: 'active_secret', type: 'string', length: 255, nullable: true)]
  private ?string $activeSecret = null;

  /**
   * Property activeConfirmedAt
   *
   * Time the active secret was confirmed.
   */
  #[ORM\Column(name: 'active_confirmed_at', type: 'datetime_immutable', nullable: true)]
  private ?DateTimeImmutable $activeConfirmedAt = null;

  /**
   * Property pendingSecret
   *
   * Legacy plaintext pending secret retained for migration compatibility.
   */
  #[ORM\Column(name: 'pending_secret', type: 'string', length: 255, nullable: true)]
  private ?string $pendingSecret = null;

  /**
   * Property pendingCreatedAt
   *
   * Time the pending secret was generated.
   */
  #[ORM\Column(name: 'pending_created_at', type: 'datetime_immutable', nullable: true)]
  private ?DateTimeImmutable $pendingCreatedAt = null;

  /**
   * Property attempts
   *
   * Failed verification attempts already consumed.
   */
  #[ORM\Column(type: 'integer')]
  private int $attempts = 0;

  /**
   * Property maxAttempts
   *
   * Maximum failed attempts allowed for this challenge.
   */
  #[ORM\Column(name: 'max_attempts', type: 'integer')]
  private int $maxAttempts;

  /**
   * Property createdAt
   *
   * Time the challenge record was created.
   */
  #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
  private DateTimeImmutable $createdAt;

  /**
   * Property updatedAt
   *
   * Time the enrollment record was last changed.
   */
  #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
  private DateTimeImmutable $updatedAt;

  /**
   * Property disableAttempts
   *
   * Failed codes submitted while disabling TOTP.
   */
  #[ORM\Column(name: 'disable_attempts', type: 'integer', options: ['default' => 0])]
  private int $disableAttempts = 0;

  /**
   * Property disableLockedUntil
   *
   * End of the temporary disable lock, or null when unlocked.
   */
  #[ORM\Column(name: 'disable_locked_until', type: 'datetime_immutable', nullable: true)]
  private ?DateTimeImmutable $disableLockedUntil = null;
  // #endregion

  // #region Getters
  /**
   * Method getUserId
   *
   * Returns the persisted user id.
   *
   * @access public
   *
   * @return string
   */
  public function getUserId(): string
  {
    return $this->userId;
  }

  /**
   * Method getActiveSecret
   *
   * Returns the persisted active secret.
   *
   * @access public
   *
   * @return ?string
   */
  public function getActiveSecret(): ?string
  {
    return $this->activeSecret;
  }

  /**
   * Method getActiveConfirmedAt
   *
   * Returns the persisted active confirmed at.
   *
   * @access public
   *
   * @return ?DateTimeImmutable
   */
  public function getActiveConfirmedAt(): ?DateTimeImmutable
  {
    return $this->activeConfirmedAt;
  }

  /**
   * Method getPendingSecret
   *
   * Returns the persisted pending secret.
   *
   * @access public
   *
   * @return ?string
   */
  public function getPendingSecret(): ?string
  {
    return $this->pendingSecret;
  }

  /**
   * Method getPendingCreatedAt
   *
   * Returns the persisted pending created at.
   *
   * @access public
   *
   * @return ?DateTimeImmutable
   */
  public function getPendingCreatedAt(): ?DateTimeImmutable
  {
    return $this->pendingCreatedAt;
  }

  /**
   * Method getAttempts
   *
   * Returns the persisted attempts.
   *
   * @access public
   *
   * @return int
   */
  public function getAttempts(): int
  {
    return $this->attempts;
  }

  /**
   * Method getMaxAttempts
   *
   * Returns the persisted max attempts.
   *
   * @access public
   *
   * @return int
   */
  public function getMaxAttempts(): int
  {
    return $this->maxAttempts;
  }

  /**
   * Method getCreatedAt
   *
   * Returns the persisted created at.
   *
   * @access public
   *
   * @return DateTimeImmutable
   */
  public function getCreatedAt(): DateTimeImmutable
  {
    return $this->createdAt;
  }

  /**
   * Method getUpdatedAt
   *
   * Returns the persisted updated at.
   *
   * @access public
   *
   * @return DateTimeImmutable
   */
  public function getUpdatedAt(): DateTimeImmutable
  {
    return $this->updatedAt;
  }

  /**
   * Method getDisableAttempts
   *
   * Returns the persisted disable attempts.
   *
   * @access public
   *
   * @return int
   */
  public function getDisableAttempts(): int
  {
    return $this->disableAttempts;
  }

  /**
   * Method getDisableLockedUntil
   *
   * Returns the persisted disable locked until.
   *
   * @access public
   *
   * @return ?DateTimeImmutable
   */
  public function getDisableLockedUntil(): ?DateTimeImmutable
  {
    return $this->disableLockedUntil;
  }
  // #endregion

  // #region Setters
  /**
   * Method setUserId
   *
   * Updates the persisted user id.
   *
   * @access public
   *
   * @param string $userId the user identifier
   *
   * @return self the created instance
   */
  public function setUserId(string $userId): self
  {
    $this->userId = $userId;

    return $this;
  }

  /**
   * Method setActiveSecret
   *
   * Updates the persisted active secret.
   *
   * @access public
   *
   * @param ?string $activeSecret the active secret
   *
   * @return self the created instance
   */
  public function setActiveSecret(?string $activeSecret): self
  {
    $this->activeSecret = $activeSecret;

    return $this;
  }

  /**
   * Method setActiveConfirmedAt
   *
   * Updates the persisted active confirmed at.
   *
   * @access public
   *
   * @param ?DateTimeImmutable $activeConfirmedAt the active confirmed time
   *
   * @return self the created instance
   */
  public function setActiveConfirmedAt(?DateTimeImmutable $activeConfirmedAt): self
  {
    $this->activeConfirmedAt = $activeConfirmedAt;

    return $this;
  }

  /**
   * Method setPendingSecret
   *
   * Updates the persisted pending secret.
   *
   * @access public
   *
   * @param ?string $pendingSecret the pending secret
   *
   * @return self the created instance
   */
  public function setPendingSecret(?string $pendingSecret): self
  {
    $this->pendingSecret = $pendingSecret;

    return $this;
  }

  /**
   * Method setPendingCreatedAt
   *
   * Updates the persisted pending created at.
   *
   * @access public
   *
   * @param ?DateTimeImmutable $pendingCreatedAt the pending created time
   *
   * @return self the created instance
   */
  public function setPendingCreatedAt(?DateTimeImmutable $pendingCreatedAt): self
  {
    $this->pendingCreatedAt = $pendingCreatedAt;

    return $this;
  }

  /**
   * Method setAttempts
   *
   * Updates the persisted attempts.
   *
   * @access public
   *
   * @param int $attempts the attempts
   *
   * @return self the created instance
   */
  public function setAttempts(int $attempts): self
  {
    $this->attempts = $attempts;

    return $this;
  }

  /**
   * Method setMaxAttempts
   *
   * Updates the persisted max attempts.
   *
   * @access public
   *
   * @param int $maxAttempts the max attempts
   *
   * @return self the created instance
   */
  public function setMaxAttempts(int $maxAttempts): self
  {
    $this->maxAttempts = $maxAttempts;

    return $this;
  }

  /**
   * Method setCreatedAt
   *
   * Updates the persisted created at.
   *
   * @access public
   *
   * @param DateTimeImmutable $createdAt the created time
   *
   * @return self the created instance
   */
  public function setCreatedAt(DateTimeImmutable $createdAt): self
  {
    $this->createdAt = $createdAt;

    return $this;
  }

  /**
   * Method setUpdatedAt
   *
   * Updates the persisted updated at.
   *
   * @access public
   *
   * @param DateTimeImmutable $updatedAt the updated time
   *
   * @return self the created instance
   */
  public function setUpdatedAt(DateTimeImmutable $updatedAt): self
  {
    $this->updatedAt = $updatedAt;

    return $this;
  }

  /**
   * Method setDisableAttempts
   *
   * Updates the persisted disable attempts.
   *
   * @access public
   *
   * @param int $disableAttempts the disable attempts
   *
   * @return self the created instance
   */
  public function setDisableAttempts(int $disableAttempts): self
  {
    $this->disableAttempts = $disableAttempts;

    return $this;
  }

  /**
   * Method setDisableLockedUntil
   *
   * Updates the persisted disable locked until.
   *
   * @access public
   *
   * @param ?DateTimeImmutable $disableLockedUntil the disable locked until
   *
   * @return self the created instance
   */
  public function setDisableLockedUntil(?DateTimeImmutable $disableLockedUntil): self
  {
    $this->disableLockedUntil = $disableLockedUntil;

    return $this;
  }
  // #endregion
}
