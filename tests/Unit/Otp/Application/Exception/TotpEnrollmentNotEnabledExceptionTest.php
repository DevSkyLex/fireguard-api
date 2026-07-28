<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Application\Exception;

use Otp\Application\Exception\TotpEnrollmentNotEnabledException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test TotpEnrollmentNotEnabledExceptionTest.
 *
 * @category Exception Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(TotpEnrollmentNotEnabledException::class)]
final class TotpEnrollmentNotEnabledExceptionTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testForUserBuildsTheMessage(): void
  {
    $exception = TotpEnrollmentNotEnabledException::forUser('user-1');

    self::assertSame('TOTP is not enabled for user "user-1".', $exception->getMessage());
  }

  #[Test]
  public function testContextExposesTheUserId(): void
  {
    $exception = TotpEnrollmentNotEnabledException::forUser('user-1');

    self::assertSame(['userId' => 'user-1'], $exception->context());
  }
  // #endregion
}
