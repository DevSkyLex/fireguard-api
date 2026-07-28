<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Application\UseCase\Command\PasswordReset\ConfirmPasswordReset;

use Auth\Application\UseCase\Command\PasswordReset\ConfirmPasswordReset\ConfirmPasswordResetResult;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ConfirmPasswordResetResultTest.
 *
 * @category Result Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ConfirmPasswordResetResult::class)]
final class ConfirmPasswordResetResultTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testSuccessFactory(): void
  {
    $result = ConfirmPasswordResetResult::success();

    self::assertTrue($result->success);
    self::assertSame(
      'Password has been reset successfully. All active sessions have been terminated.',
      $result->message,
    );
    self::assertNull($result->errorCode);
    self::assertSame(0, $result->attemptsRemaining);
  }

  #[Test]
  public function testFailedFactory(): void
  {
    $result = ConfirmPasswordResetResult::failed(
      'The code is invalid.',
      ConfirmPasswordResetResult::ERROR_INVALID_CODE,
      2,
    );

    self::assertFalse($result->success);
    self::assertSame('The code is invalid.', $result->message);
    self::assertSame('invalid_code', $result->errorCode);
    self::assertSame(2, $result->attemptsRemaining);
  }

  #[Test]
  public function testFailedFactoryDefaultsAttemptsRemainingToZero(): void
  {
    $result = ConfirmPasswordResetResult::failed(
      'The code has expired.',
      ConfirmPasswordResetResult::ERROR_EXPIRED,
    );

    self::assertSame(0, $result->attemptsRemaining);
    self::assertSame('expired', $result->errorCode);
  }

  #[Test]
  public function testErrorCodeConstants(): void
  {
    self::assertSame('invalid_code', ConfirmPasswordResetResult::ERROR_INVALID_CODE);
    self::assertSame('expired', ConfirmPasswordResetResult::ERROR_EXPIRED);
    self::assertSame('max_attempts_exceeded', ConfirmPasswordResetResult::ERROR_MAX_ATTEMPTS);
    self::assertSame('invalid_token', ConfirmPasswordResetResult::ERROR_INVALID_TOKEN);
  }

  #[Test]
  public function testConstructorDefaults(): void
  {
    $result = new ConfirmPasswordResetResult(success: true);

    self::assertTrue($result->success);
    self::assertNull($result->message);
    self::assertNull($result->errorCode);
    self::assertSame(0, $result->attemptsRemaining);
  }
  // #endregion
}
