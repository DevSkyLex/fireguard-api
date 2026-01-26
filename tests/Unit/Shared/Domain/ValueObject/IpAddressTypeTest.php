<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain\ValueObject;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\ValueObject\IpAddressType;

/**
 * Test IpAddressTypeTest.
 *
 * @category Value Object Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(IpAddressType::class)]
final class IpAddressTypeTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testLabels(): void
  {
    self::assertSame('IPv4', IpAddressType::IPV4->label());
    self::assertSame('IPv6', IpAddressType::IPV6->label());
  }
  // #endregion
}
