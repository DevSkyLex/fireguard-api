<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Application\Exception;

use Otp\Application\Exception\TotpPendingEnrollmentNotFoundException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test TotpPendingEnrollmentNotFoundExceptionTest.
 *
 * @category Exception Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(TotpPendingEnrollmentNotFoundException::class)]
final class TotpPendingEnrollmentNotFoundExceptionTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testForUserBuildsTheMessage(): void
  {
    $exception = TotpPendingEnrollmentNotFoundException::forUser('user-2');

    self::assertSame(
      'No pending TOTP setup found for user "user-2". Call setup first.',
      $exception->getMessage(),
    );
  }

  #[Test]
  public function testContextExposesTheUserId(): void
  {
    $exception = TotpPendingEnrollmentNotFoundException::forUser('user-2');

    self::assertSame(['userId' => 'user-2'], $exception->context());
  }
  // #endregion
}
