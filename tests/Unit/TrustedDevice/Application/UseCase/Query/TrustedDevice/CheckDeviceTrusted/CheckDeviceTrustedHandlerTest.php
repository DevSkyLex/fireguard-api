<?php

declare(strict_types=1);

namespace Tests\Unit\TrustedDevice\Application\UseCase\Query\TrustedDevice\CheckDeviceTrusted;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TrustedDevice\Application\Port\Outbound\TrustedDeviceRepositoryPort;
use TrustedDevice\Application\UseCase\Query\TrustedDevice\CheckDeviceTrusted\{CheckDeviceTrustedHandler, CheckDeviceTrustedQuery, CheckDeviceTrustedResult};
use TrustedDevice\Domain\Model\TrustedDevice\TrustedDevice;
use TrustedDevice\Domain\ValueObject\{DeviceFingerprint, TrustedDeviceId};

use function hash;

/**
 * Test CheckDeviceTrustedHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: CheckDeviceTrustedHandler::class)]
final class CheckDeviceTrustedHandlerTest extends TestCase
{
  // #region Methods
  /**
   * Method testInvokeReturnsNotTrustedWhenNoDevice.
   *
   * Test that __invoke returns notTrusted when device is missing.
   */
  #[Test]
  public function testInvokeReturnsNotTrustedWhenNoDevice(): void
  {
    /** @var TrustedDeviceRepositoryPort&MockObject $repository */
    $repository = $this->createMock(TrustedDeviceRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findByToken')
      ->willReturn(null);

    $handler = new CheckDeviceTrustedHandler(repository: $repository);
    $query = new CheckDeviceTrustedQuery(token: 'plain-token', userId: 'user-123');

    $result = $handler->__invoke(query: $query);

    self::assertInstanceOf(CheckDeviceTrustedResult::class, $result);
    self::assertFalse($result->trusted);
  }

  /**
   * Method testInvokeReturnsNotTrustedWhenTokenInvalid.
   *
   * Test that __invoke returns notTrusted when token does not verify.
   */
  #[Test]
  public function testInvokeReturnsNotTrustedWhenTokenInvalid(): void
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
      ->method('findByToken')
      ->with(hash('sha256', 'wrong-token'))
      ->willReturn($device);
    $repository->expects(self::never())->method('save');

    $handler = new CheckDeviceTrustedHandler(repository: $repository);
    $query = new CheckDeviceTrustedQuery(token: 'wrong-token', userId: 'user-123');

    $result = $handler->__invoke(query: $query);

    self::assertFalse($result->trusted);
  }

  /**
   * Method testInvokeReturnsNotTrustedWhenUserMismatch.
   *
   * Test that __invoke returns notTrusted when user mismatch occurs.
   */
  #[Test]
  public function testInvokeReturnsNotTrustedWhenUserMismatch(): void
  {
    $device = TrustedDevice::trust(
      id: new TrustedDeviceId('123e4567-e89b-12d3-a456-426614174001'),
      userId: 'user-123',
      fingerprint: DeviceFingerprint::create('Mozilla/5.0', null, null),
      ttlDays: 30,
    );

    $plain = $device->token()->plain();

    /** @var TrustedDeviceRepositoryPort&MockObject $repository */
    $repository = $this->createMock(TrustedDeviceRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findByToken')
      ->with(hash('sha256', $plain))
      ->willReturn($device);
    $repository->expects(self::never())->method('save');

    $handler = new CheckDeviceTrustedHandler(repository: $repository);
    $query = new CheckDeviceTrustedQuery(token: $plain, userId: 'other-user');

    $result = $handler->__invoke(query: $query);

    self::assertFalse($result->trusted);
  }

  /**
   * Method testInvokeMarksTrustedWhenValid.
   *
   * Test that __invoke returns trusted and saves device when valid.
   */
  #[Test]
  public function testInvokeMarksTrustedWhenValid(): void
  {
    $device = TrustedDevice::trust(
      id: new TrustedDeviceId('123e4567-e89b-12d3-a456-426614174002'),
      userId: 'user-789',
      fingerprint: DeviceFingerprint::create('Mozilla/5.0', null, null),
      ttlDays: 30,
    );

    $plain = $device->token()->plain();
    $lastUsedAt = $device->lastUsedAt();

    /** @var TrustedDeviceRepositoryPort&MockObject $repository */
    $repository = $this->createMock(TrustedDeviceRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findByToken')
      ->with(hash('sha256', $plain))
      ->willReturn($device);
    $repository->expects(self::once())
      ->method('save')
      ->with($device);

    $handler = new CheckDeviceTrustedHandler(repository: $repository);
    $query = new CheckDeviceTrustedQuery(token: $plain, userId: 'user-789');

    $result = $handler->__invoke(query: $query);

    self::assertTrue($result->trusted);
    self::assertSame($device->id()->value, $result->deviceId);
    self::assertSame($device->name(), $result->deviceName);
    self::assertGreaterThanOrEqual(
      $lastUsedAt->getTimestamp(),
      $device->lastUsedAt()->getTimestamp(),
    );
  }
  // #endregion
}
