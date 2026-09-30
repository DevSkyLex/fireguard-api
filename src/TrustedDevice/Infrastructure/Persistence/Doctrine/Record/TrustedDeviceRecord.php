<?php

declare(strict_types=1);

namespace TrustedDevice\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entity TrustedDeviceRecord.
 */
#[ORM\Entity]
#[ORM\Table(name: 'trusted_devices')]
#[ORM\Index(name: 'idx_td_user', columns: ['user_id'])]
#[ORM\Index(name: 'idx_td_token', columns: ['token_hash'])]
#[ORM\UniqueConstraint(name: 'uniq_td_user_fingerprint', columns: ['user_id', 'fingerprint'])]
class TrustedDeviceRecord
{
  // #region Properties
  /**
   * Property id
   *
   * Persistent identifier for this trusted-device row.
   */
  #[ORM\Id]
  #[ORM\Column(type: 'guid')]
  private string $id;

  /**
   * Property userId
   *
   * Identifier of the account that owns this trust record.
   */
  #[ORM\Column(name: 'user_id', type: 'string', length: 36)]
  private string $userId;

  /**
   * Property tokenHash
   *
   * One-way hash used to find a trust record without storing the raw bearer credential.
   */
  #[ORM\Column(name: 'token_hash', type: 'string', length: 255)]
  private string $tokenHash;

  /**
   * Property fingerprint
   *
   * Stable fingerprint hash used with the owner identifier to locate a known device.
   */
  #[ORM\Column(type: 'string', length: 255)]
  private string $fingerprint;

  /**
   * Property userAgent
   *
   * User-agent metadata shown with the trusted-device entry.
   */
  #[ORM\Column(name: 'user_agent', type: 'string', length: 500)]
  private string $userAgent;

  /**
   * Property ipAddress
   *
   * Optional IP address metadata captured for the device.
   */
  #[ORM\Column(name: 'ip_address', type: 'string', length: 45, nullable: true)]
  private ?string $ipAddress = null;

  /**
   * Property name
   *
   * Display label shown to the account owner.
   */
  #[ORM\Column(type: 'string', length: 255)]
  private string $name;

  /**
   * Property lastUsedAt
   *
   * Time the device was last accepted as trusted.
   */
  #[ORM\Column(name: 'last_used_at', type: 'datetime_immutable')]
  private DateTimeImmutable $lastUsedAt;

  /**
   * Property expiresAt
   *
   * Deadline after which this record no longer grants device trust.
   */
  #[ORM\Column(name: 'expires_at', type: 'datetime_immutable')]
  private DateTimeImmutable $expiresAt;

  /**
   * Property createdAt
   *
   * Time this trust record was created.
   */
  #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
  private DateTimeImmutable $createdAt;

  /**
   * Property revoked
   *
   * Whether the record has been revoked and is ineligible for trust checks.
   */
  #[ORM\Column(type: 'boolean')]
  private bool $revoked = false;

  // #endregion

  // #region Methods
  // Getters

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
   * Method getTokenHash
   *
   * Returns the persisted token hash.
   *
   * @access public
   *
   * @return string
   */
  public function getTokenHash(): string
  {
    return $this->tokenHash;
  }

  /**
   * Method getFingerprint
   *
   * Returns the persisted fingerprint.
   *
   * @access public
   *
   * @return string
   */
  public function getFingerprint(): string
  {
    return $this->fingerprint;
  }

  /**
   * Method getUserAgent
   *
   * Returns the persisted user agent.
   *
   * @access public
   *
   * @return string
   */
  public function getUserAgent(): string
  {
    return $this->userAgent;
  }

  /**
   * Method getIpAddress
   *
   * Returns the persisted ip address.
   *
   * @access public
   *
   * @return ?string
   */
  public function getIpAddress(): ?string
  {
    return $this->ipAddress;
  }

  /**
   * Method getName
   *
   * Returns the persisted name.
   *
   * @access public
   *
   * @return string
   */
  public function getName(): string
  {
    return $this->name;
  }

  /**
   * Method getLastUsedAt
   *
   * Returns the persisted last used at.
   *
   * @access public
   *
   * @return DateTimeImmutable
   */
  public function getLastUsedAt(): DateTimeImmutable
  {
    return $this->lastUsedAt;
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
   * Method isRevoked
   *
   * Reports whether whether the persisted revoked is true.
   *
   * @access public
   *
   * @return bool true when the record is revoked
   */
  public function isRevoked(): bool
  {
    return $this->revoked;
  }

  // Setters

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
   * Method setTokenHash
   *
   * Updates the persisted token hash.
   *
   * @access public
   *
   * @param string $tokenHash the token hash
   *
   * @return self the created instance
   */
  public function setTokenHash(string $tokenHash): self
  {
    $this->tokenHash = $tokenHash;

    return $this;
  }

  /**
   * Method setFingerprint
   *
   * Updates the persisted fingerprint.
   *
   * @access public
   *
   * @param string $fingerprint the fingerprint
   *
   * @return self the created instance
   */
  public function setFingerprint(string $fingerprint): self
  {
    $this->fingerprint = $fingerprint;

    return $this;
  }

  /**
   * Method setUserAgent
   *
   * Updates the persisted user agent.
   *
   * @access public
   *
   * @param string $userAgent the user agent
   *
   * @return self the created instance
   */
  public function setUserAgent(string $userAgent): self
  {
    $this->userAgent = $userAgent;

    return $this;
  }

  /**
   * Method setIpAddress
   *
   * Updates the persisted ip address.
   *
   * @access public
   *
   * @param ?string $ipAddress the client IP address
   *
   * @return self the created instance
   */
  public function setIpAddress(?string $ipAddress): self
  {
    $this->ipAddress = $ipAddress;

    return $this;
  }

  /**
   * Method setName
   *
   * Updates the persisted name.
   *
   * @access public
   *
   * @param string $name the name
   *
   * @return self the created instance
   */
  public function setName(string $name): self
  {
    $this->name = $name;

    return $this;
  }

  /**
   * Method setLastUsedAt
   *
   * Updates the persisted last used at.
   *
   * @access public
   *
   * @param DateTimeImmutable $lastUsedAt the last used time
   *
   * @return self the created instance
   */
  public function setLastUsedAt(DateTimeImmutable $lastUsedAt): self
  {
    $this->lastUsedAt = $lastUsedAt;

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
   * Method setRevoked
   *
   * Updates the persisted revoked.
   *
   * @access public
   *
   * @param bool $revoked the revoked
   *
   * @return self the created instance
   */
  public function setRevoked(bool $revoked): self
  {
    $this->revoked = $revoked;

    return $this;
  }
  // #endregion
}
