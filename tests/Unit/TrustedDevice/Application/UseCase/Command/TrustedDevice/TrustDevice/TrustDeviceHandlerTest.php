<?php

declare(strict_types=1);

namespace Tests\Unit\TrustedDevice\Application\UseCase\Command\TrustedDevice\TrustDevice;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Factory\UuidFactory;
use TrustedDevice\Application\Port\Outbound\TrustedDeviceRepositoryPort;
use TrustedDevice\Application\UseCase\Command\TrustedDevice\TrustDevice\{TrustDeviceCommand, TrustDeviceHandler, TrustDeviceResult};
use TrustedDevice\Domain\Model\TrustedDevice\TrustedDevice;
use TrustedDevice\Domain\ValueObject\{DeviceFingerprint, DeviceToken, TrustedDeviceId};

/**
 * Test TrustDeviceHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: TrustDeviceHandler::class)]
final class TrustDeviceHandlerTest extends TestCase
{
  // #region Methods
  /**
   * Method testInvokeCreatesNewDeviceAlways.
   *
   * Test that __invoke creates a new device
   * and deletes the old one if it exists.
   */
  #[Test]
  public function testInvokeCreatesNewDeviceAlways(): void
  {
    $command = new TrustDeviceCommand(
      userId: 'user-123',
      userAgent: 'Mozilla/5.0 (Windows NT 10.0) Chrome/120.0',
      ipAddress: '127.0.0.1',
      acceptLanguage: 'en-US',
      ttlDays: 30,
    );

    $fingerprint = DeviceFingerprint::create(
      userAgent: $command->userAgent,
      ipAddress: $command->ipAddress,
      acceptLanguage: $command->acceptLanguage,
    );

    $existing = TrustedDevice::trust(
      id: new TrustedDeviceId('123e4567-e89b-12d3-a456-426614174000'),
      userId: $command->userId,
      fingerprint: $fingerprint,
      ttlDays: 30,
    );

    $newId = new TrustedDeviceId('123e4567-e89b-12d3-a456-426614174099');

    /** @var TrustedDeviceRepositoryPort&MockObject $repository */
    $repository = $this->createMock(TrustedDeviceRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findByUserIdAndFingerprint')
      ->with($command->userId, $fingerprint->value)
      ->willReturn($existing);
    $repository->expects(self::once())
      ->method('delete')
      ->with(self::equalTo($existing->id()));
    $repository->expects(self::once())
      ->method('save');

    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::once())
      ->method('create')
      ->with(TrustedDeviceId::class)
      ->willReturn($newId);

    $handler = new TrustDeviceHandler(
      repository: $repository,
      uuidFactory: $uuidFactory,
    );

    $result = $handler->__invoke(command: $command);

    self::assertInstanceOf(TrustDeviceResult::class, $result);
    self::assertSame($newId->value, $result->deviceId);
    self::assertNotEmpty($result->token);
    self::assertSame($fingerprint->getDeviceName(), $result->deviceName);
    self::assertInstanceOf(DateTimeImmutable::class, $result->expiresAt);
  }

  /**
   * Method testInvokeCreatesNewDeviceWhenMissing.
   *
   * Test that __invoke creates a new device
   * when none exists.
   */
  #[Test]
  public function testInvokeCreatesNewDeviceWhenMissing(): void
  {
    $command = new TrustDeviceCommand(
      userId: 'user-456',
      userAgent: 'Mozilla/5.0 (Macintosh) Safari/605.1.15',
      ipAddress: null,
      acceptLanguage: null,
      ttlDays: 15,
    );

    $fingerprint = DeviceFingerprint::create(
      userAgent: $command->userAgent,
      ipAddress: $command->ipAddress,
      acceptLanguage: $command->acceptLanguage,
    );

    $deviceId = new TrustedDeviceId('123e4567-e89b-12d3-a456-426614174001');

    /** @var TrustedDeviceRepositoryPort&MockObject $repository */
    $repository = $this->createMock(TrustedDeviceRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findByUserIdAndFingerprint')
      ->with($command->userId, $fingerprint->value)
      ->willReturn(null);
    $repository->expects(self::once())
      ->method('save')
      ->with(self::isInstanceOf(TrustedDevice::class));

    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::once())
      ->method('create')
      ->with(TrustedDeviceId::class)
      ->willReturn($deviceId);

    $handler = new TrustDeviceHandler(
      repository: $repository,
      uuidFactory: $uuidFactory,
    );

    $result = $handler->__invoke(command: $command);

    self::assertSame($deviceId->value, $result->deviceId);
    self::assertNotSame('', $result->token);
    self::assertSame($fingerprint->getDeviceName(), $result->deviceName);
  }

  /**
   * Method testInvokeCreatesNewDeviceWhenExistingInvalid.
   *
   * Test that __invoke creates a new device
   * when the existing one is invalid.
   */
  #[Test]
  public function testInvokeCreatesNewDeviceWhenExistingInvalid(): void
  {
    $command = new TrustDeviceCommand(
      userId: 'user-789',
      userAgent: 'Mozilla/5.0 (Linux) Firefox/119.0',
      ipAddress: '192.168.1.10',
      acceptLanguage: null,
      ttlDays: 7,
    );

    $fingerprint = DeviceFingerprint::create(
      userAgent: $command->userAgent,
      ipAddress: $command->ipAddress,
      acceptLanguage: $command->acceptLanguage,
    );

    $expired = TrustedDevice::reconstitute(
      id: new TrustedDeviceId('123e4567-e89b-12d3-a456-426614174002'),
      userId: $command->userId,
      tokenHash: DeviceToken::generate()->hash,
      fingerprint: $fingerprint,
      name: 'Expired Device',
      lastUsedAt: new DateTimeImmutable('-2 days'),
      expiresAt: new DateTimeImmutable('-1 day'),
      createdAt: new DateTimeImmutable('-10 days'),
      revoked: false,
    );

    $newId = new TrustedDeviceId('123e4567-e89b-12d3-a456-426614174003');

    /** @var TrustedDeviceRepositoryPort&MockObject $repository */
    $repository = $this->createMock(TrustedDeviceRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findByUserIdAndFingerprint')
      ->with($command->userId, $fingerprint->value)
      ->willReturn($expired);
    $repository->expects(self::once())
      ->method('save')
      ->with(self::isInstanceOf(TrustedDevice::class));

    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::once())
      ->method('create')
      ->with(TrustedDeviceId::class)
      ->willReturn($newId);

    $handler = new TrustDeviceHandler(
      repository: $repository,
      uuidFactory: $uuidFactory,
    );

    $result = $handler->__invoke(command: $command);

    self::assertSame($newId->value, $result->deviceId);
    self::assertNotSame('', $result->token);
    self::assertSame($fingerprint->getDeviceName(), $result->deviceName);
  }
  // #endregion
}
