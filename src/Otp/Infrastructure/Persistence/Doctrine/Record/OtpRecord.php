<?php

declare(strict_types=1);

namespace Otp\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entity OtpRecord
 *
 * Doctrine entity for OTP persistence.
 *
 * @category Entity
 * @package Otp\Infrastructure\Persistence\Doctrine\Record
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'otps')]
#[ORM\Index(name: 'idx_otp_user_purpose', columns: ['user_id', 'purpose'])]
#[ORM\Index(name: 'idx_otp_expires', columns: ['expires_at'])]
class OtpRecord
{
  //#region Properties
  #[ORM\Id]
  #[ORM\Column(type: 'guid')]
  private string $id;

  #[ORM\Column(name: 'user_id', type: 'string', length: 36)]
  private string $userId;

  #[ORM\Column(name: 'challenge_token', type: 'string', length: 64, unique: true)]
  private string $challengeToken;

  #[ORM\Column(type: 'string', length: 50)]
  private string $purpose;

  #[ORM\Column(type: 'string', length: 20)]
  private string $channel;

  #[ORM\Column(name: 'code_hash', type: 'string', length: 255)]
  private string $codeHash;

  #[ORM\Column(type: 'string', length: 255)]
  private string $recipient;

  #[ORM\Column(name: 'expires_at', type: 'datetime_immutable')]
  private DateTimeImmutable $expiresAt;

  #[ORM\Column(type: 'integer')]
  private int $attempts = 0;

  #[ORM\Column(name: 'max_attempts', type: 'integer')]
  private int $maxAttempts;

  #[ORM\Column(name: 'verified_at', type: 'datetime_immutable', nullable: true)]
  private ?DateTimeImmutable $verifiedAt = null;

  #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
  private DateTimeImmutable $createdAt;
  //#endregion

  //#region Getters
  public function getId(): string
  {
    return $this->id;
  }

  public function getUserId(): string
  {
    return $this->userId;
  }

  public function getChallengeToken(): string
  {
    return $this->challengeToken;
  }

  public function getPurpose(): string
  {
    return $this->purpose;
  }

  public function getChannel(): string
  {
    return $this->channel;
  }

  public function getCodeHash(): string
  {
    return $this->codeHash;
  }

  public function getRecipient(): string
  {
    return $this->recipient;
  }

  public function getExpiresAt(): DateTimeImmutable
  {
    return $this->expiresAt;
  }

  public function getAttempts(): int
  {
    return $this->attempts;
  }

  public function getMaxAttempts(): int
  {
    return $this->maxAttempts;
  }

  public function getVerifiedAt(): ?DateTimeImmutable
  {
    return $this->verifiedAt;
  }

  public function getCreatedAt(): DateTimeImmutable
  {
    return $this->createdAt;
  }
  //#endregion

  //#region Setters
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

  public function setChallengeToken(string $challengeToken): self
  {
    $this->challengeToken = $challengeToken;
    return $this;
  }

  public function setPurpose(string $purpose): self
  {
    $this->purpose = $purpose;
    return $this;
  }

  public function setChannel(string $channel): self
  {
    $this->channel = $channel;
    return $this;
  }

  public function setCodeHash(string $codeHash): self
  {
    $this->codeHash = $codeHash;
    return $this;
  }

  public function setRecipient(string $recipient): self
  {
    $this->recipient = $recipient;
    return $this;
  }

  public function setExpiresAt(DateTimeImmutable $expiresAt): self
  {
    $this->expiresAt = $expiresAt;
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

  public function setVerifiedAt(?DateTimeImmutable $verifiedAt): self
  {
    $this->verifiedAt = $verifiedAt;
    return $this;
  }

  public function setCreatedAt(DateTimeImmutable $createdAt): self
  {
    $this->createdAt = $createdAt;
    return $this;
  }
  //#endregion
}
