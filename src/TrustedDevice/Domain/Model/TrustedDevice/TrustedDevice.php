<?php

declare(strict_types=1);

namespace TrustedDevice\Domain\Model\TrustedDevice;

use DateTimeImmutable;
use Shared\Domain\Event\DomainEvent;
use TrustedDevice\Domain\Event\{DeviceRevokedEvent, DeviceTrustedEvent};
use TrustedDevice\Domain\ValueObject\{DeviceFingerprint, DeviceToken, TrustedDeviceId, TrustedDeviceTimeline};

/**
 * Model TrustedDevice.
 *
 * @category Model
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class TrustedDevice
{
  // #region Constants
  /**
   * Constant DEFAULT_TTL_DAYS
   *
   * Default number of days before a trusted device expires.
   *
   * @access private
   *
   * @var int
   */
  private const int DEFAULT_TTL_DAYS = 30;
  // #endregion

  // #region Properties
  /**
   * @var list<DomainEvent>
   */
  private array $events = [];

  /**
   * Property revoked
   *
   * Whether this device's trust has been revoked.
   *
   * @access private
   *
   * @var bool
   */
  private bool $revoked = false;
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * Creates the trusted-device state from its identity, token and timeline.
   *
   * @access private
   *
   * @param TrustedDeviceId $id device identifier
   * @param string $userId owning user identifier
   * @param DeviceToken $token device token representation
   * @param DeviceFingerprint $fingerprint device fingerprint
   * @param string $name device display name
   * @param DateTimeImmutable $lastUsedAt last recorded use time
   * @param DateTimeImmutable $expiresAt expiration time
   * @param DateTimeImmutable $createdAt creation time
   *
   * @return void
   */
  private function __construct(
    private TrustedDeviceId $id,
    private string $userId,
    private DeviceToken $token,
    private DeviceFingerprint $fingerprint,
    private string $name,
    private DateTimeImmutable $lastUsedAt,
    private DateTimeImmutable $expiresAt,
    private DateTimeImmutable $createdAt,
  ) {
  }
  // #endregion

  // #region Factory Methods
  /**
   * Method trust.
   *
   * @static
   *
   * Creates a new trusted device.
   *
   * @param TrustedDeviceId $id the device ID
   * @param string $userId the user ID
   * @param DeviceFingerprint $fingerprint the device fingerprint
   * @param int $ttlDays days until expiration
   */
  public static function trust(
    TrustedDeviceId $id,
    string $userId,
    DeviceFingerprint $fingerprint,
    int $ttlDays = self::DEFAULT_TTL_DAYS,
  ): self {
    $now = new DateTimeImmutable();

    $device = new self(
      id: $id,
      userId: $userId,
      token: DeviceToken::generate(),
      fingerprint: $fingerprint,
      name: $fingerprint->getDeviceName(),
      lastUsedAt: $now,
      expiresAt: $now->modify("+{$ttlDays} days"),
      createdAt: $now,
    );

    $device->events[] = new DeviceTrustedEvent(
      deviceId: $id->value,
      userId: $userId,
      deviceName: $device->name,
    );

    return $device;
  }
  // #endregion

  // #region Getters
  /** Method id
   *
   * Returns the trusted-device identifier.
   *
   * @access public
   *
   * @return TrustedDeviceId the identifier
   */
  public function id(): TrustedDeviceId
  {
    return $this->id;
  }

  /** Method userId
   *
   * Returns the owning user identifier.
   *
   * @access public
   *
   * @return string the user identifier
   */
  public function userId(): string
  {
    return $this->userId;
  }

  /** Method token
   *
   * Returns the device token value object.
   *
   * @access public
   *
   * @return DeviceToken the token
   */
  public function token(): DeviceToken
  {
    return $this->token;
  }

  /** Method fingerprint
   *
   * Returns the device fingerprint.
   *
   * @access public
   *
   * @return DeviceFingerprint the fingerprint
   */
  public function fingerprint(): DeviceFingerprint
  {
    return $this->fingerprint;
  }

  /** Method name
   *
   * Returns the device display name.
   *
   * @access public
   *
   * @return string the display name
   */
  public function name(): string
  {
    return $this->name;
  }

  /** Method lastUsedAt
   *
   * Returns the most recent recorded use time.
   *
   * @access public
   *
   * @return DateTimeImmutable the last-use time
   */
  public function lastUsedAt(): DateTimeImmutable
  {
    return $this->lastUsedAt;
  }

  /** Method expiresAt
   *
   * Returns the device trust expiration time.
   *
   * @access public
   *
   * @return DateTimeImmutable the expiration time
   */
  public function expiresAt(): DateTimeImmutable
  {
    return $this->expiresAt;
  }

  /** Method createdAt
   *
   * Returns the device trust creation time.
   *
   * @access public
   *
   * @return DateTimeImmutable the creation time
   */
  public function createdAt(): DateTimeImmutable
  {
    return $this->createdAt;
  }

  /** Method isRevoked
   *
   * Reports whether the device trust has been revoked.
   *
   * @access public
   *
   * @return bool whether the device is revoked
   */
  public function isRevoked(): bool
  {
    return $this->revoked;
  }
  // #endregion

  // #region State Methods
  /**
   * Method isExpired.
   *
   * @return bool true if device trust has expired
   */
  public function isExpired(): bool
  {
    return $this->expiresAt < new DateTimeImmutable();
  }

  /**
   * Method isValid.
   *
   * @return bool true if device is still trusted
   */
  public function isValid(): bool
  {
    return !$this->isExpired() && !$this->revoked;
  }

  /**
   * Method verify.
   *
   * Verifies a token against this device.
   *
   * @param string $plainToken the plain token to verify
   *
   * @return bool true if valid
   */
  public function verify(string $plainToken): bool
  {
    if (!$this->isValid()) {
      return false;
    }

    return $this->token->verify($plainToken);
  }
  // #endregion

  // #region Behavior Methods
  /**
   * Method touch.
   *
   * Updates last used timestamp.
   */
  public function touch(): void
  {
    $this->lastUsedAt = new DateTimeImmutable();
  }

  /**
   * Method revoke.
   *
   * Revokes trust for this device.
   */
  public function revoke(): void
  {
    if ($this->revoked) {
      return;
    }

    $this->revoked = true;

    $this->events[] = new DeviceRevokedEvent(
      deviceId: $this->id->value,
      userId: $this->userId,
      deviceName: $this->name,
    );
  }

  /**
   * Method releaseEvents.
   *
   * @return list<DomainEvent>
   */
  public function releaseEvents(): array
  {
    $events = $this->events;
    $this->events = [];

    return $events;
  }
  // #endregion

  // #region Reconstitution
  /**
   * Method reconstitute
   *
   * Restores a trusted device from persisted token and timeline state.
   *
   * @access public
   *
   * @static
   *
   * @param TrustedDeviceId $id device identifier
   * @param string $userId owning user identifier
   * @param string $tokenHash persisted token hash
   * @param DeviceFingerprint $fingerprint device fingerprint
   * @param string $name device display name
   * @param TrustedDeviceTimeline $timeline persisted timestamps
   * @param bool $revoked whether trust was revoked
   *
   * @return self the restored device
   */
  public static function reconstitute(
    TrustedDeviceId $id,
    string $userId,
    string $tokenHash,
    DeviceFingerprint $fingerprint,
    string $name,
    TrustedDeviceTimeline $timeline,
    bool $revoked = false,
  ): self {
    $device = new self(
      id: $id,
      userId: $userId,
      token: DeviceToken::fromHash($tokenHash),
      fingerprint: $fingerprint,
      name: $name,
      lastUsedAt: $timeline->lastUsedAt,
      expiresAt: $timeline->expiresAt,
      createdAt: $timeline->createdAt,
    );

    $device->revoked = $revoked;

    return $device;
  }
  // #endregion
}
