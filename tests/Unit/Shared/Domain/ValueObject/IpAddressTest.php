<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain\ValueObject;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;
use Shared\Domain\ValueObject\IpAddress;

/**
 * Test IpAddressTest.
 *
 * @category ValueObject Tests
 */
#[CoversClass(className: IpAddress::class)]
final class IpAddressTest extends TestCase
{
  // #region Tests
  #[Test]
  public function testIpv4LoopbackAndReserved(): void
  {
    $ip = new IpAddress('127.0.0.1');

    self::assertTrue($ip->isIpv4());
    self::assertFalse($ip->isIpv6());
    self::assertTrue($ip->isLoopback());
    self::assertTrue($ip->isReserved());
  }

  #[Test]
  public function testPrivateIpv4(): void
  {
    $ip = new IpAddress('192.168.1.10');

    self::assertTrue($ip->isPrivate());
    self::assertFalse($ip->isLoopback());
  }

  #[Test]
  public function testIpv6(): void
  {
    $ip = new IpAddress('2001:db8::1');

    self::assertTrue($ip->isIpv6());
    self::assertFalse($ip->isIpv4());
  }

  #[Test]
  public function testEquals(): void
  {
    $ipOne = new IpAddress('8.8.8.8');
    $ipTwo = new IpAddress('8.8.8.8');
    $ipThree = new IpAddress('1.1.1.1');

    self::assertTrue($ipOne->equals($ipTwo));
    self::assertFalse($ipOne->equals($ipThree));
  }

  #[Test]
  public function testInvalidIpThrows(): void
  {
    $this->expectException(InvalidValueException::class);

    new IpAddress('invalid-ip');
  }
  // #endregion
}
