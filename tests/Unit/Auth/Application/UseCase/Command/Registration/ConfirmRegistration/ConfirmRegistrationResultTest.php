<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Application\UseCase\Command\Registration\ConfirmRegistration;

use Auth\Application\UseCase\Command\Registration\ConfirmRegistration\ConfirmRegistrationResult;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ConfirmRegistrationResultTest.
 *
 * @category Result Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ConfirmRegistrationResult::class)]
final class ConfirmRegistrationResultTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testSuccessFactoryCarriesTheIssuedTokens(): void
  {
    $result = ConfirmRegistrationResult::success(
      accessToken: 'access-token',
      refreshToken: 'refresh-token',
      tokenType: 'Bearer',
      expiresIn: 3600,
      scopes: ['openid', 'profile'],
    );

    self::assertTrue($result->success);
    self::assertSame('Your email has been verified. Welcome aboard!', $result->message);
    self::assertNull($result->errorCode);
    self::assertSame(0, $result->attemptsRemaining);
    self::assertSame('access-token', $result->accessToken);
    self::assertSame('refresh-token', $result->refreshToken);
    self::assertSame('Bearer', $result->tokenType);
    self::assertSame(3600, $result->expiresIn);
    self::assertSame(['openid', 'profile'], $result->scopes);
  }

  #[Test]
  public function testFailedFactory(): void
  {
    $result = ConfirmRegistrationResult::failed(
      'Too many attempts.',
      ConfirmRegistrationResult::ERROR_MAX_ATTEMPTS,
      0,
    );

    self::assertFalse($result->success);
    self::assertSame('Too many attempts.', $result->message);
    self::assertSame('max_attempts_exceeded', $result->errorCode);
    self::assertSame(0, $result->attemptsRemaining);
    self::assertNull($result->accessToken);
    self::assertNull($result->refreshToken);
    self::assertSame([], $result->scopes);
  }

  #[Test]
  public function testFailedFactoryKeepsRemainingAttempts(): void
  {
    $result = ConfirmRegistrationResult::failed(
      'Wrong code.',
      ConfirmRegistrationResult::ERROR_INVALID_CODE,
      3,
    );

    self::assertSame(3, $result->attemptsRemaining);
    self::assertSame('invalid_code', $result->errorCode);
  }

  #[Test]
  public function testErrorCodeConstants(): void
  {
    self::assertSame('invalid_code', ConfirmRegistrationResult::ERROR_INVALID_CODE);
    self::assertSame('expired', ConfirmRegistrationResult::ERROR_EXPIRED);
    self::assertSame('max_attempts_exceeded', ConfirmRegistrationResult::ERROR_MAX_ATTEMPTS);
    self::assertSame('invalid_token', ConfirmRegistrationResult::ERROR_INVALID_TOKEN);
  }

  #[Test]
  public function testConstructorDefaults(): void
  {
    $result = new ConfirmRegistrationResult(success: false);

    self::assertSame('Bearer', $result->tokenType);
    self::assertSame(0, $result->expiresIn);
    self::assertSame([], $result->scopes);
    self::assertNull($result->message);
  }
  // #endregion
}
