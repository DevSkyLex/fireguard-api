<?php

declare(strict_types=1);

namespace Tests\Unit\TrustedDevice\Application\UseCase\Query\TrustedDevice\ListTrustedDevices;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TrustedDevice\Application\Port\Outbound\TrustedDeviceRepositoryPort;
use TrustedDevice\Application\UseCase\Query\TrustedDevice\ListTrustedDevices\{ListTrustedDevicesHandler, ListTrustedDevicesQuery, ListTrustedDevicesResult, TrustedDeviceItemResult};
use TrustedDevice\Domain\Model\TrustedDevice\TrustedDevice;
use TrustedDevice\Domain\ValueObject\{DeviceFingerprint, DeviceToken, TrustedDeviceId};

/**
 * Test ListTrustedDevicesHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: ListTrustedDevicesHandler::class)]
final class ListTrustedDevicesHandlerTest extends TestCase
{
  // #region Methods
  /**
   * Method testInvokeReturnsOnlyValidDevices.
   *
   * Test that __invoke filters out invalid devices.
   */
  #[Test]
  public function testInvokeReturnsOnlyValidDevices(): void
  {
    $fingerprint = DeviceFingerprint::create('Mozilla/5.0', null, null);

    $validDevice = TrustedDevice::trust(
      id: new TrustedDeviceId('123e4567-e89b-12d3-a456-426614174000'),
      userId: 'user-123',
      fingerprint: $fingerprint,
      ttlDays: 30,
    );

    $expiredDevice = TrustedDevice::reconstitute(
      id: new TrustedDeviceId('123e4567-e89b-12d3-a456-426614174001'),
      userId: 'user-123',
      tokenHash: DeviceToken::generate()->hash,
      fingerprint: $fingerprint,
      name: 'Expired Device',
      lastUsedAt: new DateTimeImmutable('-2 days'),
      expiresAt: new DateTimeImmutable('-1 day'),
      createdAt: new DateTimeImmutable('-10 days'),
      revoked: false,
    );

    $revokedDevice = TrustedDevice::reconstitute(
      id: new TrustedDeviceId('123e4567-e89b-12d3-a456-426614174002'),
      userId: 'user-123',
      tokenHash: DeviceToken::generate()->hash,
      fingerprint: $fingerprint,
      name: 'Revoked Device',
      lastUsedAt: new DateTimeImmutable('-2 days'),
      expiresAt: new DateTimeImmutable('+10 days'),
      createdAt: new DateTimeImmutable('-10 days'),
      revoked: true,
    );

    /** @var TrustedDeviceRepositoryPort&MockObject $repository */
    $repository = $this->createMock(TrustedDeviceRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findAllByUserId')
      ->with('user-123')
      ->willReturn([$validDevice, $expiredDevice, $revokedDevice]);

    $handler = new ListTrustedDevicesHandler(repository: $repository);
    $query = new ListTrustedDevicesQuery(userId: 'user-123');

    $result = $handler->__invoke(query: $query);

    self::assertInstanceOf(ListTrustedDevicesResult::class, $result);
    self::assertCount(1, $result->devices);
    self::assertInstanceOf(TrustedDeviceItemResult::class, $result->devices[0]);
    self::assertSame($validDevice->id()->value, $result->devices[0]->id);
    self::assertSame($validDevice->name(), $result->devices[0]->name);
  }
  // #endregion
}
