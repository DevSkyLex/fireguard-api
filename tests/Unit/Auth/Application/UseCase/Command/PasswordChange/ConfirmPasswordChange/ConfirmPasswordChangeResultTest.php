<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Application\UseCase\Command\PasswordChange\ConfirmPasswordChange;

use Auth\Application\UseCase\Command\PasswordChange\ConfirmPasswordChange\ConfirmPasswordChangeResult;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ConfirmPasswordChangeResultTest.
 *
 * @category Result Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ConfirmPasswordChangeResult::class)]
final class ConfirmPasswordChangeResultTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testSuccessFactory(): void
  {
    $confirmation = ConfirmPasswordChangeResult::success();

    self::assertTrue($confirmation->success);
    self::assertSame(
      'Password has been changed successfully. All other sessions have been terminated.',
      $confirmation->message,
    );
    self::assertNull($confirmation->errorCode);
    self::assertSame(0, $confirmation->attemptsRemaining);
  }

  #[Test]
  public function testFailedFactory(): void
  {
    $confirmation = ConfirmPasswordChangeResult::failed(
      'The code is invalid.',
      ConfirmPasswordChangeResult::ERROR_INVALID_CODE,
      2,
    );

    self::assertFalse($confirmation->success);
    self::assertSame('The code is invalid.', $confirmation->message);
    self::assertSame('invalid_code', $confirmation->errorCode);
    self::assertSame(2, $confirmation->attemptsRemaining);
  }

  #[Test]
  public function testFailedFactoryDefaultsAttemptsRemainingToZero(): void
  {
    $confirmation = ConfirmPasswordChangeResult::failed(
      'The code has expired.',
      ConfirmPasswordChangeResult::ERROR_EXPIRED,
    );

    self::assertFalse($confirmation->success);
    self::assertSame(0, $confirmation->attemptsRemaining);
    self::assertSame('expired', $confirmation->errorCode);
  }

  #[Test]
  public function testErrorCodeConstants(): void
  {
    self::assertSame('invalid_code', ConfirmPasswordChangeResult::ERROR_INVALID_CODE);
    self::assertSame('expired', ConfirmPasswordChangeResult::ERROR_EXPIRED);
    self::assertSame('max_attempts_exceeded', ConfirmPasswordChangeResult::ERROR_MAX_ATTEMPTS);
    self::assertSame('invalid_token', ConfirmPasswordChangeResult::ERROR_INVALID_TOKEN);
    self::assertSame('same_password', ConfirmPasswordChangeResult::ERROR_SAME_PASSWORD);
  }

  #[Test]
  public function testConstructorDefaults(): void
  {
    $confirmation = new ConfirmPasswordChangeResult(success: true);

    self::assertTrue($confirmation->success);
    self::assertNull($confirmation->message);
    self::assertNull($confirmation->errorCode);
    self::assertSame(0, $confirmation->attemptsRemaining);
  }
  // #endregion
}
