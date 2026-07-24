<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Domain\Exception;

use Otp\Domain\Exception\TotpEnrollmentNoPendingSecretException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\DomainException;

/**
 * Test TotpEnrollmentNoPendingSecretExceptionTest.
 *
 * @category Exception Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(TotpEnrollmentNoPendingSecretException::class)]
final class TotpEnrollmentNoPendingSecretExceptionTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testForUserIncludesUserId(): void
  {
    $userId = '550e8400-e29b-41d4-a716-446655440001';
    $exception = TotpEnrollmentNoPendingSecretException::forUser($userId);

    $this->assertStringContainsString($userId, $exception->getMessage());
    $this->assertSame(
      'No pending TOTP enrollment for user "' . $userId . '".',
      $exception->getMessage(),
    );
  }

  #[Test]
  public function testForUserIsDomainException(): void
  {
    $exception = TotpEnrollmentNoPendingSecretException::forUser('user-42');

    $this->assertInstanceOf(DomainException::class, $exception);
    $this->assertSame('TOTP_ENROLLMENT_NO_PENDING_SECRET_EXCEPTION', $exception->code());
  }
  // #endregion
}
