<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Domain\Exception;

use Otp\Domain\Exception\OtpExpiredException;
use Otp\Domain\ValueObject\OtpId;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test OtpExpiredExceptionTest.
 *
 * @category Exception Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(OtpExpiredException::class)]
final class OtpExpiredExceptionTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testCreateIncludesId(): void
  {
    $id = new OtpId('550e8400-e29b-41d4-a716-446655440000');
    $exception = OtpExpiredException::create($id);

    $this->assertStringContainsString($id->value, $exception->getMessage());
  }
  // #endregion
}
