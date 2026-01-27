<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Application\UseCase\Command\Mfa\MfaVerify;

use Auth\Application\Port\Outbound\{JwtTokenServicePort, SessionTrackingPort};
use Auth\Application\Port\Outbound\Mfa\ChallengeVerifierPort;
use Auth\Application\UseCase\Command\Mfa\MfaVerify\{MfaVerifyCommand, MfaVerifyHandler, MfaVerifyResult};
use Auth\Domain\Exception\Session\AuthorizationException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test MfaVerifyHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: MfaVerifyHandler::class)]
final class MfaVerifyHandlerTest extends TestCase
{
  // #region Methods
  /**
   * Method testInvokeThrowsWhenPreAuthInvalid.
   */
  #[Test]
  public function testInvokeThrowsWhenPreAuthInvalid(): void
  {
    /** @var JwtTokenServicePort&MockObject $jwt */
    $jwt = $this->createMock(JwtTokenServicePort::class);
    $jwt->expects(self::once())
      ->method('decodePreAuthToken')
      ->with('pre-auth')
      ->willReturn(null);

    $handler = new MfaVerifyHandler(
      jwtService: $jwt,
      challengeVerifier: $this->createMock(ChallengeVerifierPort::class),
      sessionTracking: $this->createMock(SessionTrackingPort::class),
    );

    $this->expectException(AuthorizationException::class);
    $handler->__invoke(new MfaVerifyCommand(preAuthToken: 'pre-auth', code: '123456'));
  }

  /**
   * Method testInvokeThrowsWhenPayloadInvalid.
   */
  #[Test]
  public function testInvokeThrowsWhenPayloadInvalid(): void
  {
    /** @var JwtTokenServicePort&MockObject $jwt */
    $jwt = $this->createMock(JwtTokenServicePort::class);
    $jwt->expects(self::once())
      ->method('decodePreAuthToken')
      ->willReturn(['challenge_token' => 'challenge']);

    $handler = new MfaVerifyHandler(
      jwtService: $jwt,
      challengeVerifier: $this->createMock(ChallengeVerifierPort::class),
      sessionTracking: $this->createMock(SessionTrackingPort::class),
    );

    $this->expectException(AuthorizationException::class);
    $handler->__invoke(new MfaVerifyCommand(preAuthToken: 'pre-auth', code: '123456'));
  }

  /**
   * Method testInvokeReturnsFailureWhenVerificationFails.
   */
  #[Test]
  public function testInvokeReturnsFailureWhenVerificationFails(): void
  {
    /** @var JwtTokenServicePort&MockObject $jwt */
    $jwt = $this->createMock(JwtTokenServicePort::class);
    $jwt->expects(self::once())
      ->method('decodePreAuthToken')
      ->willReturn([
        'challenge_token' => 'challenge',
        'sub' => 'user-123',
        'email' => 'user@example.com',
        'scopes' => ['READ'],
      ]);
    $jwt->expects(self::never())->method('generateTokens');

    /** @var ChallengeVerifierPort&MockObject $verifier */
    $verifier = $this->createMock(ChallengeVerifierPort::class);
    $verifier->expects(self::once())
      ->method('verify')
      ->with('challenge', '123456')
      ->willReturn(new MfaVerifyResult(
        success: false,
        attemptsRemaining: 2,
        error: 'invalid_code',
      ));

    $handler = new MfaVerifyHandler(
      jwtService: $jwt,
      challengeVerifier: $verifier,
      sessionTracking: $this->createMock(SessionTrackingPort::class),
    );

    $result = $handler->__invoke(new MfaVerifyCommand(preAuthToken: 'pre-auth', code: '123456'));

    $this->assertFalse($result->success);
    $this->assertSame(2, $result->attemptsRemaining);
    $this->assertSame('invalid_code', $result->error);
  }

  /**
   * Method testInvokeGeneratesTokensWhenVerificationSucceeds.
   */
  #[Test]
  public function testInvokeGeneratesTokensWhenVerificationSucceeds(): void
  {
    /** @var JwtTokenServicePort&MockObject $jwt */
    $jwt = $this->createMock(JwtTokenServicePort::class);
    $jwt->expects(self::once())
      ->method('decodePreAuthToken')
      ->willReturn([
        'challenge_token' => 'challenge',
        'sub' => 'user-123',
        'email' => 'user@example.com',
        'scopes' => ['READ', 123, 'WRITE'],
        'remember_me' => true,
      ]);
    $jwt->expects(self::once())
      ->method('generateTokens')
      ->with('user-123', 'user@example.com', ['READ', 'WRITE'], true)
      ->willReturn([
        'access_token' => 'access',
        'refresh_token' => 'refresh',
        'token_type' => 'Bearer',
        'expires_in' => 3600,
      ]);
    $jwt->expects(self::once())
      ->method('decodeRefreshToken')
      ->with('refresh')
      ->willReturn([
        'access_token_id' => 'access-id',
        'refresh_token_id' => 'refresh-id',
      ]);

    /** @var ChallengeVerifierPort&MockObject $verifier */
    $verifier = $this->createMock(ChallengeVerifierPort::class);
    $verifier->expects(self::once())
      ->method('verify')
      ->with('challenge', '123456')
      ->willReturn(new MfaVerifyResult(success: true, attemptsRemaining: 1));

    /** @var SessionTrackingPort&MockObject $sessionTracking */
    $sessionTracking = $this->createMock(SessionTrackingPort::class);
    $sessionTracking->expects(self::once())
      ->method('recordSession')
      ->with(
        'user-123',
        '127.0.0.1',
        'Agent',
        'access-id',
        'refresh-id',
        true,
      );

    $handler = new MfaVerifyHandler(
      jwtService: $jwt,
      challengeVerifier: $verifier,
      sessionTracking: $sessionTracking,
    );

    $command = new MfaVerifyCommand(
      preAuthToken: 'pre-auth',
      code: '123456',
      ipAddress: '127.0.0.1',
      userAgent: 'Agent',
    );

    $result = $handler->__invoke($command);

    $this->assertTrue($result->success);
    $this->assertSame('access', $result->accessToken);
    $this->assertSame('refresh', $result->refreshToken);
    $this->assertSame(['READ', 'WRITE'], $result->scopes);
  }
  // #endregion
}
