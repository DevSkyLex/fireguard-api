<?php

declare(strict_types=1);

namespace Tests\Unit\TrustedDevice\Domain\Model;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use TrustedDevice\Domain\Event\{DeviceRevokedEvent, DeviceTrustedEvent};
use TrustedDevice\Domain\Model\TrustedDevice\TrustedDevice;
use TrustedDevice\Domain\ValueObject\{DeviceFingerprint, DeviceToken, TrustedDeviceId};

use function usleep;

/**
 * Test TrustedDeviceTest.
 *
 * @category Unit Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: TrustedDevice::class)]
final class TrustedDeviceTest extends TestCase
{
  // #region Methods
  /**
   * Method testTrustCreatesDeviceAndEvents.
   *
   * Tests that trust creates a device
   * and emits the trusted event.
   */
  #[Test]
  public function testTrustCreatesDeviceAndEvents(): void
  {
    $id = new TrustedDeviceId('123e4567-e89b-12d3-a456-426614174000');
    $fingerprint = DeviceFingerprint::create(
      userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0',
      ipAddress: '127.0.0.1',
      acceptLanguage: 'en-US',
    );

    $device = TrustedDevice::trust(
      id: $id,
      userId: 'user-123',
      fingerprint: $fingerprint,
      ttlDays: 30,
    );

    self::assertSame($id, $device->id());
    self::assertSame('user-123', $device->userId());
    self::assertSame($fingerprint, $device->fingerprint());
    self::assertSame($fingerprint->getDeviceName(), $device->name());
    self::assertFalse($device->isRevoked());
    self::assertGreaterThan(
      $device->createdAt()->getTimestamp(),
      $device->expiresAt()->getTimestamp(),
    );

    $events = $device->releaseEvents();
    self::assertCount(1, $events);
    self::assertInstanceOf(DeviceTrustedEvent::class, $events[0]);

    self::assertSame([], $device->releaseEvents());
  }

  /**
   * Method testVerifyUsesTokenAndRevocation.
   *
   * Tests that verify checks token validity
   * and revocation state.
   */
  #[Test]
  public function testVerifyUsesTokenAndRevocation(): void
  {
    $device = TrustedDevice::trust(
      id: new TrustedDeviceId('123e4567-e89b-12d3-a456-426614174001'),
      userId: 'user-123',
      fingerprint: DeviceFingerprint::create('Mozilla/5.0', null, null),
      ttlDays: 30,
    );

    $plain = $device->token()->plain();

    self::assertTrue($device->verify($plain));
    self::assertFalse($device->verify('wrong-token'));

    $device->releaseEvents();
    $device->revoke();

    self::assertFalse($device->verify($plain));

    $events = $device->releaseEvents();
    self::assertCount(1, $events);
    self::assertInstanceOf(DeviceRevokedEvent::class, $events[0]);
  }

  #[Test]
  public function testRevokeIsIdempotent(): void
  {
    $device = TrustedDevice::trust(
      id: new TrustedDeviceId('123e4567-e89b-12d3-a456-426614174004'),
      userId: 'user-123',
      fingerprint: DeviceFingerprint::create('Mozilla/5.0', null, null),
      ttlDays: 30,
    );

    $device->releaseEvents();
    $device->revoke();
    $device->releaseEvents();

    $device->revoke();

    self::assertSame([], $device->releaseEvents());
  }

  /**
   * Method testTouchUpdatesLastUsedAt.
   *
   * Tests that touch updates last used timestamp.
   */
  #[Test]
  public function testTouchUpdatesLastUsedAt(): void
  {
    $device = TrustedDevice::trust(
      id: new TrustedDeviceId('123e4567-e89b-12d3-a456-426614174002'),
      userId: 'user-123',
      fingerprint: DeviceFingerprint::create('Mozilla/5.0', null, null),
      ttlDays: 30,
    );

    $lastUsedAt = $device->lastUsedAt();

    usleep(1000);
    $device->touch();

    self::assertGreaterThanOrEqual(
      $lastUsedAt->getTimestamp(),
      $device->lastUsedAt()->getTimestamp(),
    );
  }

  /**
   * Method testReconstituteSupportsExpiredAndRevokedStates.
   *
   * Tests that reconstitute respects
   * expiration and revocation flags.
   */
  #[Test]
  public function testReconstituteSupportsExpiredAndRevokedStates(): void
  {
    $token = DeviceToken::generate();
    $fingerprint = DeviceFingerprint::fromHash(
      hash: 'hash',
      userAgent: 'Mozilla/5.0',
      ipAddress: null,
    );

    $device = TrustedDevice::reconstitute(
      id: new TrustedDeviceId('123e4567-e89b-12d3-a456-426614174003'),
      userId: 'user-456',
      tokenHash: $token->hash,
      fingerprint: $fingerprint,
      name: 'Test Device',
      lastUsedAt: new DateTimeImmutable('-2 days'),
      expiresAt: new DateTimeImmutable('-1 day'),
      createdAt: new DateTimeImmutable('-10 days'),
      revoked: true,
    );

    self::assertSame('user-456', $device->userId());
    self::assertSame('Test Device', $device->name());
    self::assertTrue($device->isExpired());
    self::assertTrue($device->isRevoked());
    self::assertFalse($device->isValid());
    self::assertFalse($device->verify($token->plain()));
  }
  // #endregion
}
