<?php

declare(strict_types=1);

namespace Tests\Unit\TrustedDevice\Infrastructure\Adapter\Auth;

use Error;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use TrustedDevice\Application\UseCase\Query\TrustedDevice\CheckDeviceTrusted\{CheckDeviceTrustedQuery, CheckDeviceTrustedResult};
use TrustedDevice\Infrastructure\Adapter\Auth\TrustedDeviceCheckAdapter;

/**
 * Test TrustedDeviceCheckAdapterTest.
 *
 * This adapter decides whether MFA can be skipped, so its fail-closed
 * behaviour is a security control: any failure while checking the device
 * token must be answered with "not trusted", never with an exception that
 * could be swallowed further up into a trusted verdict.
 *
 * @category Adapter Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(TrustedDeviceCheckAdapter::class)]
final class TrustedDeviceCheckAdapterTest extends TestCase
{
  private const string USER_ID = '550e8400-e29b-41d4-a716-446655496001';

  private const string DEVICE_TOKEN = 'device-token-abc';

  #[Test]
  public function testItForwardsTheUserAndTokenToTheQueryBus(): void
  {
    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static fn (CheckDeviceTrustedQuery $query): bool => self::USER_ID === $query->userId
        && self::DEVICE_TOKEN === $query->token))
      ->willReturn(new CheckDeviceTrustedResult(trusted: true, deviceId: 'device-1', deviceName: 'Laptop'));

    self::assertTrue(new TrustedDeviceCheckAdapter($queryBus)->isTrusted(self::USER_ID, self::DEVICE_TOKEN));
  }

  #[Test]
  public function testItReportsAnUntrustedDevice(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturn(new CheckDeviceTrustedResult(trusted: false));

    self::assertFalse(new TrustedDeviceCheckAdapter($queryBus)->isTrusted(self::USER_ID, self::DEVICE_TOKEN));
  }

  #[Test]
  public function testItFailsClosedWhenTheQueryBusThrows(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(new RuntimeException('device store unreachable'));

    self::assertFalse(new TrustedDeviceCheckAdapter($queryBus)->isTrusted(self::USER_ID, self::DEVICE_TOKEN));
  }

  #[Test]
  public function testItFailsClosedOnAnEngineError(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(new Error('unexpected engine failure'));

    self::assertFalse(new TrustedDeviceCheckAdapter($queryBus)->isTrusted(self::USER_ID, self::DEVICE_TOKEN));
  }
}
