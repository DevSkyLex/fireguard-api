<?php

declare(strict_types=1);

namespace Tests\Unit\TrustedDevice\Application\UseCase\Command\TrustedDevice\RevokeDevice;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TrustedDevice\Application\Port\Outbound\TrustedDeviceRepositoryPort;
use TrustedDevice\Application\UseCase\Command\TrustedDevice\RevokeDevice\{RevokeDeviceCommand, RevokeDeviceHandler, RevokeDeviceResult};
use TrustedDevice\Domain\Exception\TrustedDeviceNotFoundException;
use TrustedDevice\Domain\Model\TrustedDevice\TrustedDevice;
use TrustedDevice\Domain\ValueObject\{DeviceFingerprint, DeviceToken, TrustedDeviceId};

/**
 * Test RevokeDeviceHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: RevokeDeviceHandler::class)]
final class RevokeDeviceHandlerTest extends TestCase
{
  // #region Methods
  /**
   * Method testInvokeRevokesDeviceForOwner.
   *
   * Test that __invoke revokes a device
   * for the matching user.
   */
  #[Test]
  public function testInvokeRevokesDeviceForOwner(): void
  {
    $device = TrustedDevice::trust(
      id: new TrustedDeviceId('123e4567-e89b-12d3-a456-426614174000'),
      userId: 'user-123',
      fingerprint: DeviceFingerprint::create('Mozilla/5.0', null, null),
      ttlDays: 30,
    );

    /** @var TrustedDeviceRepositoryPort&MockObject $repository */
    $repository = $this->createMock(TrustedDeviceRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
      ->with(self::isInstanceOf(TrustedDeviceId::class))
      ->willReturn($device);
    $repository->expects(self::once())
      ->method('save')
      ->with($device);

    $handler = new RevokeDeviceHandler(repository: $repository);

    $command = new RevokeDeviceCommand(
      deviceId: $device->id()->value,
      userId: 'user-123',
    );

    $result = $handler->__invoke(command: $command);

    self::assertInstanceOf(RevokeDeviceResult::class, $result);
    self::assertTrue($result->success);
    self::assertTrue($device->isRevoked());
  }

  /**
   * Method testInvokeThrowsWhenDeviceMissing.
   *
   * Test that __invoke throws when device is missing.
   */
  #[Test]
  public function testInvokeThrowsWhenDeviceMissing(): void
  {
    /** @var TrustedDeviceRepositoryPort&MockObject $repository */
    $repository = $this->createMock(TrustedDeviceRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
      ->with(self::isInstanceOf(TrustedDeviceId::class))
      ->willReturn(null);

    $handler = new RevokeDeviceHandler(repository: $repository);

    $command = new RevokeDeviceCommand(
      deviceId: '123e4567-e89b-12d3-a456-426614174001',
      userId: 'user-123',
    );

    $this->expectException(TrustedDeviceNotFoundException::class);
    $handler->__invoke(command: $command);
  }

  /**
   * Method testInvokeThrowsWhenUserMismatch.
   *
   * Test that __invoke throws when user does not own device.
   */
  #[Test]
  public function testInvokeThrowsWhenUserMismatch(): void
  {
    $device = TrustedDevice::reconstitute(
      id: new TrustedDeviceId('123e4567-e89b-12d3-a456-426614174002'),
      userId: 'user-123',
      tokenHash: DeviceToken::generate()->hash,
      fingerprint: DeviceFingerprint::create('Mozilla/5.0', null, null),
      name: 'Device',
      lastUsedAt: new DateTimeImmutable('-1 day'),
      expiresAt: new DateTimeImmutable('+30 days'),
      createdAt: new DateTimeImmutable('-10 days'),
      revoked: false,
    );

    /** @var TrustedDeviceRepositoryPort&MockObject $repository */
    $repository = $this->createMock(TrustedDeviceRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
      ->with(self::isInstanceOf(TrustedDeviceId::class))
      ->willReturn($device);

    $handler = new RevokeDeviceHandler(repository: $repository);

    $command = new RevokeDeviceCommand(
      deviceId: $device->id()->value,
      userId: 'other-user',
    );

    $this->expectException(TrustedDeviceNotFoundException::class);
    $handler->__invoke(command: $command);
  }
  // #endregion
}
