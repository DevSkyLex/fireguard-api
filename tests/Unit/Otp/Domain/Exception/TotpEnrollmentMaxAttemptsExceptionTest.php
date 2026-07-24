<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Domain\Exception;

use Otp\Domain\Exception\TotpEnrollmentMaxAttemptsException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\DomainException;

/**
 * Test TotpEnrollmentMaxAttemptsExceptionTest.
 *
 * @category Exception Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(TotpEnrollmentMaxAttemptsException::class)]
final class TotpEnrollmentMaxAttemptsExceptionTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testForUserIncludesUserId(): void
  {
    $userId = '550e8400-e29b-41d4-a716-446655440001';
    $exception = TotpEnrollmentMaxAttemptsException::forUser($userId);

    $this->assertStringContainsString($userId, $exception->getMessage());
  }

  #[Test]
  public function testForUserIsDomainException(): void
  {
    $exception = TotpEnrollmentMaxAttemptsException::forUser('user-42');

    $this->assertInstanceOf(DomainException::class, $exception);
    $this->assertSame('TOTP_ENROLLMENT_MAX_ATTEMPTS_EXCEPTION', $exception->code());
  }
  // #endregion
}
