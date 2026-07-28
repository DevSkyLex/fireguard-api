<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Application\UseCase\Command\Registration\ResendRegistration;

use Auth\Application\UseCase\Command\Registration\ResendRegistration\ResendRegistrationResult;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ResendRegistrationResultTest.
 *
 * @category Result Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ResendRegistrationResult::class)]
final class ResendRegistrationResultTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testSuccessFactoryCarriesTheChallengePayload(): void
  {
    $expiresAt = new DateTimeImmutable('2026-06-06T18:45:00+00:00');

    $result = ResendRegistrationResult::success(
      challengeToken: 'challenge',
      maskedRecipient: 'n***w@example.com',
      expiresAt: $expiresAt,
      maxAttempts: 5,
      canResendIn: 30,
    );

    self::assertTrue($result->success);
    self::assertSame('A new verification code has been sent.', $result->message);
    self::assertNull($result->errorCode);
    self::assertSame(0, $result->retryAfter);
    self::assertSame('challenge', $result->challengeToken);
    self::assertSame('n***w@example.com', $result->maskedRecipient);
    self::assertSame($expiresAt, $result->expiresAt);
    self::assertSame(5, $result->maxAttempts);
    self::assertSame(30, $result->canResendIn);
  }

  #[Test]
  public function testSuccessFactoryAcceptsACustomMessage(): void
  {
    $result = ResendRegistrationResult::success(message: 'Code sent again.');

    self::assertSame('Code sent again.', $result->message);
    self::assertNull($result->challengeToken);
  }

  #[Test]
  public function testFailedFactory(): void
  {
    $result = ResendRegistrationResult::failed(
      'Please wait before requesting a new code.',
      ResendRegistrationResult::ERROR_RESEND_NOT_ALLOWED,
      25,
    );

    self::assertFalse($result->success);
    self::assertSame('Please wait before requesting a new code.', $result->message);
    self::assertSame('resend_not_allowed', $result->errorCode);
    self::assertSame(25, $result->retryAfter);
    self::assertNull($result->challengeToken);
  }

  #[Test]
  public function testFailedFactoryDefaultsRetryAfterToZero(): void
  {
    $result = ResendRegistrationResult::failed(
      'Unknown token.',
      ResendRegistrationResult::ERROR_INVALID_TOKEN,
    );

    self::assertSame(0, $result->retryAfter);
    self::assertSame('invalid_token', $result->errorCode);
  }

  #[Test]
  public function testErrorCodeConstants(): void
  {
    self::assertSame('invalid_token', ResendRegistrationResult::ERROR_INVALID_TOKEN);
    self::assertSame('resend_not_allowed', ResendRegistrationResult::ERROR_RESEND_NOT_ALLOWED);
  }

  #[Test]
  public function testConstructorDefaults(): void
  {
    $result = new ResendRegistrationResult(success: true);

    self::assertTrue($result->success);
    self::assertSame(0, $result->retryAfter);
    self::assertNull($result->maskedRecipient);
  }
  // #endregion
}
