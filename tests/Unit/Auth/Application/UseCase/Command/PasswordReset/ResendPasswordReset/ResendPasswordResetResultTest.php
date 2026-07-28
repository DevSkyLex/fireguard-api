<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Application\UseCase\Command\PasswordReset\ResendPasswordReset;

use Auth\Application\UseCase\Command\PasswordReset\ResendPasswordReset\ResendPasswordResetResult;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ResendPasswordResetResultTest.
 *
 * @category Result Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ResendPasswordResetResult::class)]
final class ResendPasswordResetResultTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testSuccessFactoryCarriesTheWholeChallenge(): void
  {
    $expiresAt = new DateTimeImmutable('2026-01-05T10:15:00+00:00');

    $resend = ResendPasswordResetResult::success(
      challengeToken: 'challenge-token-1',
      maskedRecipient: 'j***e@example.com',
      expiresAt: $expiresAt,
      maxAttempts: 5,
      canResendIn: 60,
      message: 'A brand new code is on its way.',
    );

    self::assertTrue($resend->success);
    self::assertSame('A brand new code is on its way.', $resend->message);
    self::assertNull($resend->errorCode);
    self::assertSame(0, $resend->retryAfter);
    self::assertSame('challenge-token-1', $resend->challengeToken);
    self::assertSame('j***e@example.com', $resend->maskedRecipient);
    self::assertSame($expiresAt, $resend->expiresAt);
    self::assertSame(5, $resend->maxAttempts);
    self::assertSame(60, $resend->canResendIn);
  }

  #[Test]
  public function testSuccessFactoryDefaultsEveryArgument(): void
  {
    $resend = ResendPasswordResetResult::success();

    self::assertTrue($resend->success);
    self::assertSame('A new password reset code has been sent.', $resend->message);
    self::assertNull($resend->errorCode);
    self::assertSame(0, $resend->retryAfter);
    self::assertNull($resend->challengeToken);
    self::assertNull($resend->maskedRecipient);
    self::assertNull($resend->expiresAt);
    self::assertNull($resend->maxAttempts);
    self::assertNull($resend->canResendIn);
  }

  #[Test]
  public function testFailedFactoryWithARetryAfterDelay(): void
  {
    $resend = ResendPasswordResetResult::failed(
      'Please wait before requesting a new code.',
      ResendPasswordResetResult::ERROR_RESEND_NOT_ALLOWED,
      45,
    );

    self::assertFalse($resend->success);
    self::assertSame('Please wait before requesting a new code.', $resend->message);
    self::assertSame('resend_not_allowed', $resend->errorCode);
    self::assertSame(45, $resend->retryAfter);
    self::assertNull($resend->challengeToken);
    self::assertNull($resend->canResendIn);
  }

  #[Test]
  public function testFailedFactoryDefaultsRetryAfterToZero(): void
  {
    $resend = ResendPasswordResetResult::failed(
      'Invalid or expired reset token.',
      ResendPasswordResetResult::ERROR_INVALID_TOKEN,
    );

    self::assertFalse($resend->success);
    self::assertSame('invalid_token', $resend->errorCode);
    self::assertSame(0, $resend->retryAfter);
  }

  #[Test]
  public function testErrorCodeConstants(): void
  {
    self::assertSame('invalid_token', ResendPasswordResetResult::ERROR_INVALID_TOKEN);
    self::assertSame('resend_not_allowed', ResendPasswordResetResult::ERROR_RESEND_NOT_ALLOWED);
  }

  #[Test]
  public function testConstructorDefaults(): void
  {
    $resend = new ResendPasswordResetResult(success: true);

    self::assertTrue($resend->success);
    self::assertNull($resend->message);
    self::assertNull($resend->errorCode);
    self::assertSame(0, $resend->retryAfter);
    self::assertNull($resend->challengeToken);
    self::assertNull($resend->maskedRecipient);
    self::assertNull($resend->expiresAt);
    self::assertNull($resend->maxAttempts);
    self::assertNull($resend->canResendIn);
  }
  // #endregion
}
