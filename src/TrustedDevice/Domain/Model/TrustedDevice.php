<?php

declare(strict_types=1);

namespace TrustedDevice\Domain\Model;

use DateTimeImmutable;
use Shared\Domain\Event\DomainEvent;
use TrustedDevice\Domain\Event\DeviceRevokedEvent;
use TrustedDevice\Domain\Event\DeviceTrustedEvent;
use TrustedDevice\Domain\ValueObject\DeviceFingerprint;
use TrustedDevice\Domain\ValueObject\DeviceToken;
use TrustedDevice\Domain\ValueObject\TrustedDeviceId;

/**
 * Model TrustedDevice.
 *
 * @category Model
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class TrustedDevice
{
  // #region Constants
  private const int DEFAULT_TTL_DAYS = 30;
  // #endregion

  // #region Properties
  /** @var list<DomainEvent> */
  private array $events = [];

  private bool $revoked = false;
  // #endregion

  // #region Constructor
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
  public function id(): TrustedDeviceId
  {
    return $this->id;
  }

  public function userId(): string
  {
    return $this->userId;
  }

  public function token(): DeviceToken
  {
    return $this->token;
  }

  public function fingerprint(): DeviceFingerprint
  {
    return $this->fingerprint;
  }

  public function name(): string
  {
    return $this->name;
  }

  public function lastUsedAt(): DateTimeImmutable
  {
    return $this->lastUsedAt;
  }

  public function expiresAt(): DateTimeImmutable
  {
    return $this->expiresAt;
  }

  public function createdAt(): DateTimeImmutable
  {
    return $this->createdAt;
  }

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
  public static function reconstitute(
    TrustedDeviceId $id,
    string $userId,
    string $tokenHash,
    DeviceFingerprint $fingerprint,
    string $name,
    DateTimeImmutable $lastUsedAt,
    DateTimeImmutable $expiresAt,
    DateTimeImmutable $createdAt,
    bool $revoked = false,
  ): self {
    $device = new self(
      id: $id,
      userId: $userId,
      token: DeviceToken::fromHash($tokenHash),
      fingerprint: $fingerprint,
      name: $name,
      lastUsedAt: $lastUsedAt,
      expiresAt: $expiresAt,
      createdAt: $createdAt,
    );

    $device->revoked = $revoked;

    return $device;
  }
  // #endregion
}
