<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Application\UseCase\Command\Mfa\MfaResend;

use Auth\Application\UseCase\Command\Mfa\MfaResend\MfaResendResult;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test MfaResendResultTest.
 *
 * @category Result Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MfaResendResult::class)]
final class MfaResendResultTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testSuccessFactoryCarriesTheChallengePayload(): void
  {
    $expiresAt = new DateTimeImmutable('2026-01-01T10:00:00+00:00');

    $result = MfaResendResult::success(
      preAuthToken: 'pre-auth',
      challengeToken: 'challenge',
      mfaMethod: 'email',
      mfaDestination: 'j***e@example.com',
      expiresAt: $expiresAt,
      maxAttempts: 5,
      canResendIn: 30,
    );

    self::assertTrue($result->success);
    self::assertSame('A new MFA code has been sent.', $result->message);
    self::assertNull($result->errorCode);
    self::assertSame(0, $result->retryAfter);
    self::assertSame('pre-auth', $result->preAuthToken);
    self::assertSame('challenge', $result->challengeToken);
    self::assertSame('email', $result->mfaMethod);
    self::assertSame('j***e@example.com', $result->mfaDestination);
    self::assertSame($expiresAt, $result->expiresAt);
    self::assertSame(5, $result->maxAttempts);
    self::assertSame(30, $result->canResendIn);
  }

  #[Test]
  public function testSuccessFactoryAcceptsACustomMessage(): void
  {
    $result = MfaResendResult::success(
      preAuthToken: 'pre-auth',
      challengeToken: 'challenge',
      mfaMethod: 'sms',
      mfaDestination: '+33******89',
      expiresAt: new DateTimeImmutable('2026-01-01T10:00:00+00:00'),
      maxAttempts: 3,
      canResendIn: 60,
      message: 'Code resent by SMS.',
    );

    self::assertSame('Code resent by SMS.', $result->message);
    self::assertSame('sms', $result->mfaMethod);
  }

  #[Test]
  public function testFailedFactory(): void
  {
    $result = MfaResendResult::failed(
      'Resend is not allowed yet.',
      MfaResendResult::ERROR_RESEND_NOT_ALLOWED,
      45,
    );

    self::assertFalse($result->success);
    self::assertSame('Resend is not allowed yet.', $result->message);
    self::assertSame('resend_not_allowed', $result->errorCode);
    self::assertSame(45, $result->retryAfter);
    self::assertNull($result->preAuthToken);
    self::assertNull($result->challengeToken);
    self::assertNull($result->expiresAt);
    self::assertNull($result->maxAttempts);
    self::assertNull($result->canResendIn);
  }

  #[Test]
  public function testFailedFactoryDefaultsRetryAfterToZero(): void
  {
    $result = MfaResendResult::failed('Unknown challenge.', MfaResendResult::ERROR_INVALID_CHALLENGE);

    self::assertSame(0, $result->retryAfter);
    self::assertSame('invalid_challenge', $result->errorCode);
  }

  #[Test]
  public function testErrorCodeConstants(): void
  {
    self::assertSame('invalid_challenge', MfaResendResult::ERROR_INVALID_CHALLENGE);
    self::assertSame('resend_not_allowed', MfaResendResult::ERROR_RESEND_NOT_ALLOWED);
    self::assertSame('totp_not_resendable', MfaResendResult::ERROR_TOTP_NOT_RESENDABLE);
  }

  #[Test]
  public function testConstructorDefaults(): void
  {
    $result = new MfaResendResult(success: false);

    self::assertFalse($result->success);
    self::assertNull($result->message);
    self::assertNull($result->mfaDestination);
    self::assertSame(0, $result->retryAfter);
  }
  // #endregion
}
