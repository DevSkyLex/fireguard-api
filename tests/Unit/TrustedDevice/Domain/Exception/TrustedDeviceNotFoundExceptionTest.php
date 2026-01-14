<?php

declare(strict_types=1);

namespace Tests\Unit\TrustedDevice\Domain\Exception;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use TrustedDevice\Domain\Exception\TrustedDeviceNotFoundException;

/**
 * Test TrustedDeviceNotFoundExceptionTest.
 *
 * @category Unit Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: TrustedDeviceNotFoundException::class)]
final class TrustedDeviceNotFoundExceptionTest extends TestCase
{
  // #region Methods
  /**
   * Method testCreate.
   *
   * Tests that create sets the expected message.
   */
  #[Test]
  public function testCreate(): void
  {
    $exception = TrustedDeviceNotFoundException::create(id: 'device-123');

    self::assertSame('TrustedDevice with ID "device-123" not found.', $exception->getMessage());
  }
  // #endregion
}
