<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Application\UseCase\Command\PasswordChange\RequestPasswordChange;

use Auth\Application\UseCase\Command\PasswordChange\RequestPasswordChange\RequestPasswordChangeResult;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test RequestPasswordChangeResultTest.
 *
 * @category Result Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(RequestPasswordChangeResult::class)]
final class RequestPasswordChangeResultTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testSuccessFactoryCarriesTheWholeChallenge(): void
  {
    $expiresAt = new DateTimeImmutable('2026-01-05T10:15:00+00:00');

    $request = RequestPasswordChangeResult::success(
      challengeToken: 'challenge-token-1',
      maskedRecipient: 'j***e@example.com',
      expiresAt: $expiresAt,
      maxAttempts: 5,
    );

    self::assertTrue($request->success);
    self::assertSame(
      'A verification code has been sent to your email address.',
      $request->message,
    );
    self::assertNull($request->errorCode);
    self::assertSame('challenge-token-1', $request->challengeToken);
    self::assertSame('j***e@example.com', $request->maskedRecipient);
    self::assertSame($expiresAt, $request->expiresAt);
    self::assertSame(5, $request->maxAttempts);
  }

  #[Test]
  public function testSuccessFactoryDefaultsTheOptionalChallengeMetadataToNull(): void
  {
    $request = RequestPasswordChangeResult::success(challengeToken: 'challenge-token-2');

    self::assertTrue($request->success);
    self::assertSame('challenge-token-2', $request->challengeToken);
    self::assertNull($request->maskedRecipient);
    self::assertNull($request->expiresAt);
    self::assertNull($request->maxAttempts);
  }

  #[Test]
  public function testFailedFactory(): void
  {
    $request = RequestPasswordChangeResult::failed(
      'The current password is incorrect.',
      RequestPasswordChangeResult::ERROR_INVALID_PASSWORD,
    );

    self::assertFalse($request->success);
    self::assertSame('The current password is incorrect.', $request->message);
    self::assertSame('invalid_password', $request->errorCode);
    self::assertNull($request->challengeToken);
    self::assertNull($request->maskedRecipient);
    self::assertNull($request->expiresAt);
    self::assertNull($request->maxAttempts);
  }

  #[Test]
  public function testErrorCodeConstants(): void
  {
    self::assertSame('invalid_password', RequestPasswordChangeResult::ERROR_INVALID_PASSWORD);
    self::assertSame('user_not_found', RequestPasswordChangeResult::ERROR_USER_NOT_FOUND);
  }

  #[Test]
  public function testConstructorDefaults(): void
  {
    $request = new RequestPasswordChangeResult(success: false);

    self::assertFalse($request->success);
    self::assertNull($request->message);
    self::assertNull($request->errorCode);
    self::assertNull($request->challengeToken);
    self::assertNull($request->maskedRecipient);
    self::assertNull($request->expiresAt);
    self::assertNull($request->maxAttempts);
  }
  // #endregion
}
