<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Application\UseCase\Command\PasswordReset\RequestPasswordReset;

use Auth\Application\UseCase\Command\PasswordReset\RequestPasswordReset\RequestPasswordResetResult;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test RequestPasswordResetResultTest.
 *
 * @category Result Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(RequestPasswordResetResult::class)]
final class RequestPasswordResetResultTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testSuccessFactoryCarriesTheChallengePayload(): void
  {
    $expiresAt = new DateTimeImmutable('2026-03-04T08:30:00+00:00');

    $result = RequestPasswordResetResult::success(
      challengeToken: 'challenge',
      maskedRecipient: 'j***e@example.com',
      expiresAt: $expiresAt,
      maxAttempts: 5,
      canResendIn: 60,
    );

    self::assertTrue($result->success);
    self::assertSame(
      'If an account exists with this email, you will receive a password reset code.',
      $result->message,
    );
    self::assertSame('challenge', $result->challengeToken);
    self::assertSame('j***e@example.com', $result->maskedRecipient);
    self::assertSame($expiresAt, $result->expiresAt);
    self::assertSame(5, $result->maxAttempts);
    self::assertSame(60, $result->canResendIn);
  }

  #[Test]
  public function testSuccessFactoryWithoutChallengeStaysNeutral(): void
  {
    $result = RequestPasswordResetResult::success();

    self::assertTrue($result->success);
    self::assertNull($result->challengeToken);
    self::assertNull($result->maskedRecipient);
    self::assertNull($result->expiresAt);
    self::assertNull($result->maxAttempts);
    self::assertNull($result->canResendIn);
  }

  #[Test]
  public function testConstructorDefaults(): void
  {
    $result = new RequestPasswordResetResult(success: false);

    self::assertFalse($result->success);
    self::assertNull($result->message);
    self::assertNull($result->challengeToken);
  }
  // #endregion
}
