<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Domain\Exception;

use Otp\Domain\Exception\OtpMaxAttemptsException;
use Otp\Domain\ValueObject\OtpId;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test OtpMaxAttemptsExceptionTest.
 *
 * @category Exception Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(OtpMaxAttemptsException::class)]
final class OtpMaxAttemptsExceptionTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testCreateIncludesId(): void
  {
    $id = new OtpId('550e8400-e29b-41d4-a716-446655440001');
    $exception = OtpMaxAttemptsException::create($id);

    $this->assertStringContainsString($id->value, $exception->getMessage());
  }
  // #endregion
}
