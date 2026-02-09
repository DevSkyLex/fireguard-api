<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Presentation\Api\Processor\Auth;

use ApiPlatform\Metadata\Post;
use Auth\Application\Port\Outbound\{JwtTokenServicePort, SessionTrackingPort};
use Auth\Application\Port\Outbound\Mfa\ChallengeVerifierPort;
use Auth\Application\UseCase\Command\Mfa\MfaVerify\{MfaVerifyHandler, MfaVerifyResult};
use Auth\Presentation\Api\Dto\Input\Auth\MfaVerifyInput;
use Auth\Presentation\Api\Dto\Output\Auth\LoginOutput;
use Auth\Presentation\Api\Processor\Auth\MfaVerifyProcessor;
use Auth\Presentation\Api\Service\RefreshTokenCookieService;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{BadRequestHttpException, UnauthorizedHttpException};

/**
 * Test MfaVerifyProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: MfaVerifyProcessor::class)]
final class MfaVerifyProcessorTest extends TestCase
{
  // #region Methods
  /**
   * Method testProcessThrowsUnauthorizedOnAuthError.
   */
  #[Test]
  public function testProcessThrowsUnauthorizedOnAuthError(): void
  {
    /** @var JwtTokenServicePort&MockObject $jwt */
    $jwt = $this->createMock(JwtTokenServicePort::class);
    $jwt->expects(self::once())
      ->method('decodePreAuthToken')
      ->willReturn(null);

    $handler = new MfaVerifyHandler(
      jwtService: $jwt,
      challengeVerifier: $this->createMock(ChallengeVerifierPort::class),
      sessionTracking: $this->createMock(SessionTrackingPort::class),
    );

    $processor = new MfaVerifyProcessor(
      handler: $handler,
      requestStack: new RequestStack(),
      cookieService: $this->createMock(RefreshTokenCookieService::class),
    );

    $input = new MfaVerifyInput();
    $input->preAuthToken = 'pre-auth';
    $input->code = '123456';

    $this->expectException(UnauthorizedHttpException::class);
    $processor->process($input, new Post());
  }

  #[Test]
  public function testProcessThrowsBadRequestWhenCodeInvalid(): void
  {
    $handler = new MfaVerifyHandler(
      jwtService: $this->createMock(JwtTokenServicePort::class),
      challengeVerifier: $this->createMock(ChallengeVerifierPort::class),
      sessionTracking: $this->createMock(SessionTrackingPort::class),
    );

    $processor = new MfaVerifyProcessor(
      handler: $handler,
      requestStack: new RequestStack(),
      cookieService: $this->createMock(RefreshTokenCookieService::class),
    );

    $input = new MfaVerifyInput();
    $input->preAuthToken = 'pre-auth';
    $input->code = '12ab';

    $this->expectException(BadRequestHttpException::class);
    $processor->process($input, new Post());
  }

  /**
   * Method testProcessThrowsBadRequestWhenVerificationFails.
   */
  #[Test]
  public function testProcessThrowsBadRequestWhenVerificationFails(): void
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
      ->willReturn(new MfaVerifyResult(success: false, attemptsRemaining: 1, error: 'invalid_code'));

    $handler = new MfaVerifyHandler(
      jwtService: $jwt,
      challengeVerifier: $verifier,
      sessionTracking: $this->createMock(SessionTrackingPort::class),
    );

    $processor = new MfaVerifyProcessor(
      handler: $handler,
      requestStack: new RequestStack(),
      cookieService: $this->createMock(RefreshTokenCookieService::class),
    );

    $input = new MfaVerifyInput();
    $input->preAuthToken = 'pre-auth';
    $input->code = '123456';

    $this->expectException(BadRequestHttpException::class);
    $processor->process($input, new Post());
  }

  /**
   * Method testProcessReturnsOutputAndSetsCookieOnSuccess.
   */
  #[Test]
  public function testProcessReturnsOutputAndSetsCookieOnSuccess(): void
  {
    /** @var JwtTokenServicePort&MockObject $jwt */
    $jwt = $this->createMock(JwtTokenServicePort::class);
    $jwt->expects(self::once())
      ->method('decodePreAuthToken')
      ->willReturn([
        'challenge_token' => 'challenge',
        'sub' => 'user-123',
        'email' => 'user@example.com',
        'scopes' => ['READ', 'WRITE'],
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

    $request = new Request();
    $request->headers->set('User-Agent', 'Agent');
    $request->server->set('REMOTE_ADDR', '127.0.0.1');
    $requestStack = new RequestStack();
    $requestStack->push($request);

    $cookieService = new RefreshTokenCookieService(
      environment: 'test',
      cookieBaseName: 'refresh_token',
      lifetimeShort: 3600,
      lifetimeLong: 7200,
    );

    $processor = new MfaVerifyProcessor(
      handler: $handler,
      requestStack: $requestStack,
      cookieService: $cookieService,
    );

    $input = new MfaVerifyInput();
    $input->preAuthToken = 'pre-auth';
    $input->code = '123456';

    $output = $processor->process($input, new Post());

    $this->assertInstanceOf(LoginOutput::class, $output);
    $this->assertSame('access', $output->accessToken);
    $this->assertSame('Bearer', $output->tokenType);
    $this->assertSame(3600, $output->expiresIn);
    $this->assertSame('READ WRITE', $output->scope);

    $cookie = $request->attributes->get('_refresh_token_cookie');
    $this->assertInstanceOf(\Symfony\Component\HttpFoundation\Cookie::class, $cookie);
    $this->assertSame('refresh', $cookie->getValue());
  }
  // #endregion
}
