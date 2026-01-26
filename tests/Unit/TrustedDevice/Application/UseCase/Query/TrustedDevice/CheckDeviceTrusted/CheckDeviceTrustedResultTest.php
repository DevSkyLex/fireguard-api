<?php

declare(strict_types=1);

namespace Tests\Unit\TrustedDevice\Application\UseCase\Query\TrustedDevice\CheckDeviceTrusted;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use TrustedDevice\Application\UseCase\Query\TrustedDevice\CheckDeviceTrusted\CheckDeviceTrustedResult;

/**
 * Test CheckDeviceTrustedResultTest.
 *
 * @category Result Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CheckDeviceTrustedResult::class)]
final class CheckDeviceTrustedResultTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testTrustedFactory(): void
  {
    $result = CheckDeviceTrustedResult::trusted('device-1', 'My Device');

    self::assertTrue($result->trusted);
    self::assertSame('device-1', $result->deviceId);
    self::assertSame('My Device', $result->deviceName);
  }

  #[Test]
  public function testNotTrustedFactory(): void
  {
    $result = CheckDeviceTrustedResult::notTrusted();

    self::assertFalse($result->trusted);
    self::assertNull($result->deviceId);
    self::assertNull($result->deviceName);
  }
  // #endregion
}
