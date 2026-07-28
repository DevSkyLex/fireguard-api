<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Application\UseCase\Command\Registration\RegisterUser;

use Auth\Application\UseCase\Command\Registration\RegisterUser\RegisterUserResult;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test RegisterUserResultTest.
 *
 * @category Result Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(RegisterUserResult::class)]
final class RegisterUserResultTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testSuccessFactoryCarriesTheVerificationChallenge(): void
  {
    $expiresAt = new DateTimeImmutable('2026-05-05T12:00:00+00:00');

    $result = RegisterUserResult::success(
      challengeToken: 'challenge',
      maskedRecipient: 'n***w@example.com',
      expiresAt: $expiresAt,
      maxAttempts: 5,
      canResendIn: 60,
    );

    self::assertTrue($result->success);
    self::assertSame(
      'Your account has been created. Enter the verification code we sent to your email.',
      $result->message,
    );
    self::assertNull($result->errorCode);
    self::assertSame('challenge', $result->challengeToken);
    self::assertSame('n***w@example.com', $result->maskedRecipient);
    self::assertSame($expiresAt, $result->expiresAt);
    self::assertSame(5, $result->maxAttempts);
    self::assertSame(60, $result->canResendIn);
  }

  #[Test]
  public function testFailedFactory(): void
  {
    $result = RegisterUserResult::failed(
      'This email is already registered.',
      RegisterUserResult::ERROR_EMAIL_TAKEN,
    );

    self::assertFalse($result->success);
    self::assertSame('This email is already registered.', $result->message);
    self::assertSame('email_taken', $result->errorCode);
    self::assertNull($result->challengeToken);
    self::assertNull($result->maskedRecipient);
    self::assertNull($result->expiresAt);
    self::assertNull($result->maxAttempts);
    self::assertNull($result->canResendIn);
  }

  #[Test]
  public function testErrorCodeConstant(): void
  {
    self::assertSame('email_taken', RegisterUserResult::ERROR_EMAIL_TAKEN);
  }

  #[Test]
  public function testConstructorDefaults(): void
  {
    $result = new RegisterUserResult(success: true);

    self::assertTrue($result->success);
    self::assertNull($result->message);
    self::assertNull($result->errorCode);
    self::assertNull($result->challengeToken);
  }
  // #endregion
}
