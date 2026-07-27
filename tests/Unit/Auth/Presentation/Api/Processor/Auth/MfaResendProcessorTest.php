<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Presentation\Api\Processor\Auth;

use ApiPlatform\Metadata\Post;
use Auth\Application\Port\Outbound\JwtTokenServicePort;
use Auth\Application\UseCase\Command\Mfa\MfaResend\MfaResendHandler;
use Auth\Presentation\Api\Dto\Input\Auth\MfaResendInput;
use Auth\Presentation\Api\Processor\Auth\MfaResendProcessor;
use DateTimeImmutable;
use Otp\Application\Contract\Challenge\{ChallengeInfo, OtpChannel};
use Otp\Application\Port\Inbound\Challenge\OtpChallengePort;
use Otp\Application\Port\Outbound\Challenge\OtpRepositoryPort;
use Otp\Domain\Model\Otp;
use Otp\Domain\ValueObject\{
  ChallengeToken,
  OtpChannel as DomainOtpChannel,
  OtpCode,
  OtpId,
  OtpPurpose as DomainOtpPurpose
};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{
  BadRequestHttpException,
  NotFoundHttpException,
  TooManyRequestsHttpException,
  UnauthorizedHttpException
};
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

use function hash;
use function sprintf;
use function substr;

/**
 * Test MfaResendProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MfaResendProcessor::class)]
#[CoversClass(MfaResendHandler::class)]
final class MfaResendProcessorTest extends TestCase
{
  // #region Constants
  private const string PRE_AUTH_TOKEN = 'pre-auth-token';

  private const string CHALLENGE_TOKEN = 'challenge-token';

  private const string USER_ID = 'user-123';

  private const string IP = '127.0.0.1';
  // #endregion

  // #region Methods
  #[Test]
  public function testProcessIssuesAFreshChallengeAndReturnsTheMfaPayload(): void
  {
    $otpRepository = $this->createStub(OtpRepositoryPort::class);
    $otpRepository->method('findByChallengeToken')->willReturn($this->pendingEmailOtp('-10 minutes'));

    $output = $this->createProcessor($otpRepository)->process($this->input(), new Post());

    self::assertTrue($output->mfaRequired);
    self::assertSame('new-pre-auth-token', $output->mfaToken);
    self::assertSame('new-challenge-token', $output->challengeToken);
    self::assertSame(OtpChannel::EMAIL->value, $output->mfaMethod);
    self::assertSame('u***@example.com', $output->mfaDestination);
  }

  #[Test]
  public function testProcessThrowsUnauthorizedWhenThePreAuthTokenCannotBeDecoded(): void
  {
    $jwtService = $this->createStub(JwtTokenServicePort::class);
    $jwtService->method('decodePreAuthToken')->willReturn(null);

    $processor = $this->createProcessor(
      $this->createStub(OtpRepositoryPort::class),
      jwtService: $jwtService,
    );

    $this->expectException(UnauthorizedHttpException::class);

    $processor->process($this->input(), new Post());
  }

  #[Test]
  public function testProcessThrowsUnauthorizedWhenThePreAuthPayloadIsIncomplete(): void
  {
    $jwtService = $this->createStub(JwtTokenServicePort::class);
    $jwtService->method('decodePreAuthToken')->willReturn(['sub' => self::USER_ID]);

    $processor = $this->createProcessor(
      $this->createStub(OtpRepositoryPort::class),
      jwtService: $jwtService,
    );

    $this->expectException(UnauthorizedHttpException::class);

    $processor->process($this->input(), new Post());
  }

  #[Test]
  public function testProcessThrowsNotFoundWhenTheChallengeIsUnknown(): void
  {
    $otpRepository = $this->createStub(OtpRepositoryPort::class);
    $otpRepository->method('findByChallengeToken')->willReturn(null);

    $this->expectException(NotFoundHttpException::class);

    $this->createProcessor($otpRepository)->process($this->input(), new Post());
  }

  #[Test]
  public function testProcessThrowsTooManyRequestsWhileTheResendCooldownIsRunning(): void
  {
    $otpRepository = $this->createStub(OtpRepositoryPort::class);
    $otpRepository->method('findByChallengeToken')->willReturn($this->pendingEmailOtp('now'));

    $this->expectException(TooManyRequestsHttpException::class);

    $this->createProcessor($otpRepository)->process($this->input(), new Post());
  }

  #[Test]
  public function testProcessThrowsBadRequestWhenTheChallengeIsTotp(): void
  {
    $otpRepository = $this->createStub(OtpRepositoryPort::class);
    $otpRepository->method('findByChallengeToken')->willReturn($this->pendingTotpOtp());

    $this->expectException(BadRequestHttpException::class);

    $this->createProcessor($otpRepository)->process($this->input(), new Post());
  }

  #[Test]
  public function testProcessThrowsTooManyRequestsWhenTheRateLimitIsExhausted(): void
  {
    $rateLimiter = $this->createRateLimiterFactory(limit: 1);
    $rateLimiter->create($this->rateLimitKey())->consume();

    $otpRepository = $this->createStub(OtpRepositoryPort::class);
    $otpRepository->method('findByChallengeToken')->willReturn($this->pendingEmailOtp('-10 minutes'));

    $processor = $this->createProcessor($otpRepository, rateLimiter: $rateLimiter);

    $this->expectException(TooManyRequestsHttpException::class);

    $processor->process($this->input(), new Post());
  }

  private function input(): MfaResendInput
  {
    $input = new MfaResendInput();
    $input->preAuthToken = self::PRE_AUTH_TOKEN;

    return $input;
  }

  private function createProcessor(
    OtpRepositoryPort $otpRepository,
    ?JwtTokenServicePort $jwtService = null,
    ?RateLimiterFactory $rateLimiter = null,
  ): MfaResendProcessor {
    if (null === $jwtService) {
      $jwtService = $this->createStub(JwtTokenServicePort::class);
      $jwtService->method('decodePreAuthToken')->willReturn([
        'sub' => self::USER_ID,
        'challenge_token' => self::CHALLENGE_TOKEN,
        'email' => 'user@example.com',
        'scopes' => ['openid'],
        'remember_me' => true,
      ]);
      $jwtService->method('generatePreAuthToken')->willReturn('new-pre-auth-token');
    }

    $otpChallenge = $this->createStub(OtpChallengePort::class);
    $otpChallenge->method('generate')->willReturn(new ChallengeInfo(
      challengeToken: 'new-challenge-token',
      maskedRecipient: 'u***@example.com',
      expiresAt: new DateTimeImmutable('+10 minutes'),
      maxAttempts: 5,
    ));

    $handler = new MfaResendHandler(
      jwtService: $jwtService,
      otpRepository: $otpRepository,
      otpChallenge: $otpChallenge,
    );

    $requestStack = new RequestStack();
    $requestStack->push(new Request(server: ['REMOTE_ADDR' => self::IP]));

    return new MfaResendProcessor(
      handler: $handler,
      requestStack: $requestStack,
      rateLimiter: $rateLimiter ?? $this->createRateLimiterFactory(),
    );
  }

  private function createRateLimiterFactory(int $limit = 10): RateLimiterFactory
  {
    return new RateLimiterFactory(
      config: [
        'id' => 'mfa_resend',
        'policy' => 'fixed_window',
        'limit' => $limit,
        'interval' => '1 hour',
      ],
      storage: new InMemoryStorage(),
    );
  }

  private function rateLimitKey(): string
  {
    return sprintf(
      'mfa_resend_%s_%s',
      substr(hash('sha256', self::PRE_AUTH_TOKEN), 0, 16),
      substr(hash('sha256', self::IP), 0, 16),
    );
  }

  private function pendingEmailOtp(string $createdAt): Otp
  {
    return Otp::reconstitute(
      id: new OtpId('123e4567-e89b-12d3-a456-426614174100'),
      challengeToken: ChallengeToken::fromString(self::CHALLENGE_TOKEN),
      userId: self::USER_ID,
      purpose: DomainOtpPurpose::LOGIN,
      channel: DomainOtpChannel::EMAIL,
      codeHash: OtpCode::generate()->hash(),
      recipient: 'user@example.com',
      expiresAt: new DateTimeImmutable('+10 minutes'),
      maxAttempts: 5,
      attempts: 0,
      verifiedAt: null,
      createdAt: new DateTimeImmutable($createdAt),
    );
  }

  private function pendingTotpOtp(): Otp
  {
    return Otp::reconstitute(
      id: new OtpId('123e4567-e89b-12d3-a456-426614174200'),
      challengeToken: ChallengeToken::fromString(self::CHALLENGE_TOKEN),
      userId: self::USER_ID,
      purpose: DomainOtpPurpose::LOGIN,
      channel: DomainOtpChannel::TOTP,
      codeHash: OtpCode::generate()->hash(),
      recipient: 'user@example.com',
      expiresAt: new DateTimeImmutable('+10 minutes'),
      maxAttempts: 5,
      attempts: 0,
      verifiedAt: null,
      createdAt: new DateTimeImmutable('-10 minutes'),
    );
  }
  // #endregion
}
