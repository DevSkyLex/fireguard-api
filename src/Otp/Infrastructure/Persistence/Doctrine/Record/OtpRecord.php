<?php

declare(strict_types=1);

namespace Otp\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entity OtpRecord.
 *
 * Doctrine entity for OTP persistence.
 *
 * @category Entity
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
  // #region Properties
  /**
   * Property id
   *
   * Database identifier for this challenge record.
   */
  #[ORM\Id]
  #[ORM\Column(type: 'guid')]
  private string $id;

  /**
   * Property userId
   *
   * Identifier of the user who owns this challenge.
   */
  #[ORM\Column(name: 'user_id', type: 'string', length: 36)]
  private string $userId;

  /**
   * Property challengeToken
   *
   * Opaque token presented to locate this challenge.
   */
  #[ORM\Column(name: 'challenge_token', type: 'string', length: 64, unique: true)]
  private string $challengeToken;

  /**
   * Property purpose
   *
   * Verification purpose accepted for the challenge.
   */
  #[ORM\Column(type: 'string', length: 50)]
  private string $purpose;

  /**
   * Property channel
   *
   * Delivery channel selected for the challenge.
   */
  #[ORM\Column(type: 'string', length: 20)]
  private string $channel;

  /**
   * Property codeHash
   *
   * Hash checked against submitted one-time codes; the code itself is not stored.
   */
  #[ORM\Column(name: 'code_hash', type: 'string', length: 255)]
  private string $codeHash;

  /**
   * Property recipient
   *
   * Destination address or phone number used for delivery.
   */
  #[ORM\Column(type: 'string', length: 255)]
  private string $recipient;

  /**
   * Property expiresAt
   *
   * Deadline after which verification is rejected.
   */
  #[ORM\Column(name: 'expires_at', type: 'datetime_immutable')]
  private DateTimeImmutable $expiresAt;

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
   * Property verifiedAt
   *
   * Time of successful verification, or null while unverified.
   */
  #[ORM\Column(name: 'verified_at', type: 'datetime_immutable', nullable: true)]
  private ?DateTimeImmutable $verifiedAt = null;

  /**
   * Property createdAt
   *
   * Time the challenge record was created.
   */
  #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
  private DateTimeImmutable $createdAt;
  // #endregion

  // #region Getters
  /**
   * Method getId
   *
   * Returns the persisted id.
   *
   * @access public
   *
   * @return string
   */
  public function getId(): string
  {
    return $this->id;
  }

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
   * Method getChallengeToken
   *
   * Returns the persisted challenge token.
   *
   * @access public
   *
   * @return string
   */
  public function getChallengeToken(): string
  {
    return $this->challengeToken;
  }

  /**
   * Method getPurpose
   *
   * Returns the persisted purpose.
   *
   * @access public
   *
   * @return string
   */
  public function getPurpose(): string
  {
    return $this->purpose;
  }

  /**
   * Method getChannel
   *
   * Returns the persisted channel.
   *
   * @access public
   *
   * @return string
   */
  public function getChannel(): string
  {
    return $this->channel;
  }

  /**
   * Method getCodeHash
   *
   * Returns the persisted code hash.
   *
   * @access public
   *
   * @return string
   */
  public function getCodeHash(): string
  {
    return $this->codeHash;
  }

  /**
   * Method getRecipient
   *
   * Returns the persisted recipient.
   *
   * @access public
   *
   * @return string
   */
  public function getRecipient(): string
  {
    return $this->recipient;
  }

  /**
   * Method getExpiresAt
   *
   * Returns the persisted expires at.
   *
   * @access public
   *
   * @return DateTimeImmutable
   */
  public function getExpiresAt(): DateTimeImmutable
  {
    return $this->expiresAt;
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
   * Method getVerifiedAt
   *
   * Returns the persisted verified at.
   *
   * @access public
   *
   * @return ?DateTimeImmutable
   */
  public function getVerifiedAt(): ?DateTimeImmutable
  {
    return $this->verifiedAt;
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
  // #endregion

  // #region Setters
  /**
   * Method setId
   *
   * Updates the persisted id.
   *
   * @access public
   *
   * @param string $id the identifier
   *
   * @return self the created instance
   */
  public function setId(string $id): self
  {
    $this->id = $id;

    return $this;
  }

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
   * Method setChallengeToken
   *
   * Updates the persisted challenge token.
   *
   * @access public
   *
   * @param string $challengeToken the public challenge token
   *
   * @return self the created instance
   */
  public function setChallengeToken(string $challengeToken): self
  {
    $this->challengeToken = $challengeToken;

    return $this;
  }

  /**
   * Method setPurpose
   *
   * Updates the persisted purpose.
   *
   * @access public
   *
   * @param string $purpose the purpose
   *
   * @return self the created instance
   */
  public function setPurpose(string $purpose): self
  {
    $this->purpose = $purpose;

    return $this;
  }

  /**
   * Method setChannel
   *
   * Updates the persisted channel.
   *
   * @access public
   *
   * @param string $channel the channel
   *
   * @return self the created instance
   */
  public function setChannel(string $channel): self
  {
    $this->channel = $channel;

    return $this;
  }

  /**
   * Method setCodeHash
   *
   * Updates the persisted code hash.
   *
   * @access public
   *
   * @param string $codeHash the code hash
   *
   * @return self the created instance
   */
  public function setCodeHash(string $codeHash): self
  {
    $this->codeHash = $codeHash;

    return $this;
  }

  /**
   * Method setRecipient
   *
   * Updates the persisted recipient.
   *
   * @access public
   *
   * @param string $recipient the recipient
   *
   * @return self the created instance
   */
  public function setRecipient(string $recipient): self
  {
    $this->recipient = $recipient;

    return $this;
  }

  /**
   * Method setExpiresAt
   *
   * Updates the persisted expires at.
   *
   * @access public
   *
   * @param DateTimeImmutable $expiresAt the expires time
   *
   * @return self the created instance
   */
  public function setExpiresAt(DateTimeImmutable $expiresAt): self
  {
    $this->expiresAt = $expiresAt;

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
   * Method setVerifiedAt
   *
   * Updates the persisted verified at.
   *
   * @access public
   *
   * @param ?DateTimeImmutable $verifiedAt the verified time
   *
   * @return self the created instance
   */
  public function setVerifiedAt(?DateTimeImmutable $verifiedAt): self
  {
    $this->verifiedAt = $verifiedAt;

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
  // #endregion
}
