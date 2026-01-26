<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Domain\Exception;

use Otp\Domain\Exception\OtpNotFoundException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test OtpNotFoundExceptionTest.
 *
 * @category Exception Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(OtpNotFoundException::class)]
final class OtpNotFoundExceptionTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testCreateIncludesId(): void
  {
    $exception = OtpNotFoundException::create('otp-123');

    $this->assertStringContainsString('otp-123', $exception->getMessage());
  }
  // #endregion
}
