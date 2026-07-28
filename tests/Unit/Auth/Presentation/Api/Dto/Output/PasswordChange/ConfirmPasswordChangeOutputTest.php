<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Presentation\Api\Dto\Output\PasswordChange;

use Auth\Presentation\Api\Dto\Output\PasswordChange\ConfirmPasswordChangeOutput;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ConfirmPasswordChangeOutputTest.
 *
 * @category Output DTO Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ConfirmPasswordChangeOutput::class)]
final class ConfirmPasswordChangeOutputTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testItExposesTheSuccessPayload(): void
  {
    $output = new ConfirmPasswordChangeOutput(
      success: true,
      message: 'Password has been changed successfully.',
    );

    self::assertTrue($output->success);
    self::assertSame('Password has been changed successfully.', $output->message);
    self::assertNull($output->errorCode);
    self::assertSame(0, $output->attemptsRemaining);
  }

  #[Test]
  public function testItExposesTheFailurePayload(): void
  {
    $output = new ConfirmPasswordChangeOutput(
      success: false,
      message: 'The verification code is invalid.',
      errorCode: 'invalid_code',
      attemptsRemaining: 2,
    );

    self::assertFalse($output->success);
    self::assertSame('The verification code is invalid.', $output->message);
    self::assertSame('invalid_code', $output->errorCode);
    self::assertSame(2, $output->attemptsRemaining);
  }
  // #endregion
}
