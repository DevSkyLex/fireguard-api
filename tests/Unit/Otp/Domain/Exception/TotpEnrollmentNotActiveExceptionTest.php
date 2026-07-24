<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Domain\Exception;

use Otp\Domain\Exception\TotpEnrollmentNotActiveException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\DomainException;

/**
 * Test TotpEnrollmentNotActiveExceptionTest.
 *
 * @category Exception Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(TotpEnrollmentNotActiveException::class)]
final class TotpEnrollmentNotActiveExceptionTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testForUserIncludesUserId(): void
  {
    $userId = '550e8400-e29b-41d4-a716-446655440001';
    $exception = TotpEnrollmentNotActiveException::forUser($userId);

    $this->assertStringContainsString($userId, $exception->getMessage());
    $this->assertSame(
      'No active TOTP enrollment for user "550e8400-e29b-41d4-a716-446655440001".',
      $exception->getMessage(),
    );
  }

  #[Test]
  public function testForUserIsDomainException(): void
  {
    $exception = TotpEnrollmentNotActiveException::forUser('user-42');

    $this->assertInstanceOf(DomainException::class, $exception);
    $this->assertSame('TOTP_ENROLLMENT_NOT_ACTIVE_EXCEPTION', $exception->code());
  }
  // #endregion
}
