<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Application\UseCase\Command\Mfa\MfaResend;

use Auth\Application\Port\Outbound\JwtTokenServicePort;
use Auth\Application\UseCase\Command\Mfa\MfaResend\{MfaResendCommand, MfaResendHandler, MfaResendResult};
use Auth\Domain\ValueObject\Security\SignInGrantType;
use DateTimeImmutable;
use Otp\Application\Contract\Challenge\ChallengeInfo;
use Otp\Application\Port\Inbound\Challenge\OtpChallengePort;
use Otp\Application\Port\Outbound\Challenge\OtpRepositoryPort;
use Otp\Domain\Model\Otp;
use Otp\Domain\ValueObject\{ChallengeToken, OtpChannel, OtpCode, OtpId, OtpPurpose};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test MfaResendHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MfaResendHandler::class)]
final class MfaResendHandlerTest extends TestCase
{
  #[Test]
  public function testInvokeRejectsResendForTotpChannel(): void
  {
    $otp = Otp::generate(
      id: new OtpId('123e4567-e89b-12d3-a456-426614174030'),
      userId: 'user-1',
      purpose: OtpPurpose::LOGIN,
      channel: OtpChannel::TOTP,
      recipient: 'user-1',
    );

    /** @var JwtTokenServicePort&MockObject $jwtService */
    $jwtService = $this->createMock(JwtTokenServicePort::class);
    $jwtService->expects(self::once())
      ->method('decodePreAuthToken')
      ->willReturn([
        'sub' => 'user-1',
        'challenge_token' => $otp->challengeToken()->value,
        'email' => 'user@example.com',
      ]);

    /** @var OtpRepositoryPort&MockObject $otpRepository */
    $otpRepository = $this->createMock(OtpRepositoryPort::class);
    $otpRepository->expects(self::once())
      ->method('findByChallengeToken')
      ->willReturn($otp);

    /** @var OtpChallengePort&MockObject $otpChallenge */
    $otpChallenge = $this->createMock(OtpChallengePort::class);
    $otpChallenge->expects(self::never())->method('generate');

    $handler = new MfaResendHandler(
      jwtService: $jwtService,
      otpRepository: $otpRepository,
      otpChallenge: $otpChallenge,
    );

    $result = $handler->__invoke(new MfaResendCommand(preAuthToken: 'pre-auth'));

    self::assertFalse($result->success);
    self::assertSame(MfaResendResult::ERROR_TOTP_NOT_RESENDABLE, $result->errorCode);
  }

  /**
   * Preserves the original provider when an email MFA challenge is resent.
   */
  #[Test]
  public function testInvokePreservesFederatedGrantTypeWhenChallengeIsResent(): void
  {
    $otp = Otp::reconstitute(
      id: new OtpId('123e4567-e89b-12d3-a456-426614174032'),
      challengeToken: ChallengeToken::fromString('original-challenge'),
      userId: 'user-1',
      purpose: OtpPurpose::LOGIN,
      channel: OtpChannel::EMAIL,
      codeHash: OtpCode::generate()->hash(),
      recipient: 'user@example.com',
      expiresAt: new DateTimeImmutable('+10 minutes'),
      maxAttempts: 5,
      attempts: 0,
      verifiedAt: null,
      createdAt: new DateTimeImmutable('-10 minutes'),
    );

    $jwtService = $this->createMock(JwtTokenServicePort::class);
    $jwtService->method('decodePreAuthToken')->willReturn([
      'sub' => 'user-1',
      'challenge_token' => 'original-challenge',
      'email' => 'user@example.com',
      'scopes' => ['openid'],
      'grant_type' => 'federated_microsoft',
    ]);
    $jwtService->expects(self::once())
      ->method('generatePreAuthToken')
      ->willReturnCallback(static function (
        string $userId,
        string $challengeToken,
        string $email = '',
        array $scopes = [],
        int $ttl = 300,
        bool $rememberMe = false,
        SignInGrantType $grantType = SignInGrantType::PASSWORD,
      ): string {
        self::assertSame('user-1', $userId);
        self::assertSame('new-challenge', $challengeToken);
        self::assertSame(SignInGrantType::MICROSOFT, $grantType);

        return 'new-pre-auth';
      });

    $otpRepository = $this->createStub(OtpRepositoryPort::class);
    $otpRepository->method('findByChallengeToken')->willReturn($otp);

    $otpChallenge = $this->createStub(OtpChallengePort::class);
    $otpChallenge->method('generate')->willReturn(new ChallengeInfo(
      challengeToken: 'new-challenge',
      maskedRecipient: 'u***@example.com',
      expiresAt: new DateTimeImmutable('+10 minutes'),
      maxAttempts: 5,
    ));

    $handler = new MfaResendHandler(
      jwtService: $jwtService,
      otpRepository: $otpRepository,
      otpChallenge: $otpChallenge,
    );

    $result = $handler->__invoke(new MfaResendCommand(preAuthToken: 'pre-auth'));

    self::assertTrue($result->success);
    self::assertSame('new-pre-auth', $result->preAuthToken);
  }

  #[Test]
  public function testInvokeFallsBackToDefaultScopesWhenScopesClaimIsEmpty(): void
  {
    $otp = Otp::generate(
      id: new OtpId('123e4567-e89b-12d3-a456-426614174031'),
      userId: 'user-1',
      purpose: OtpPurpose::LOGIN,
      channel: OtpChannel::TOTP,
      recipient: 'user-1',
    );

    /** @var JwtTokenServicePort&MockObject $jwtService */
    $jwtService = $this->createMock(JwtTokenServicePort::class);
    $jwtService->expects(self::once())
      ->method('decodePreAuthToken')
      ->willReturn([
        'sub' => 'user-1',
        'challenge_token' => $otp->challengeToken()->value,
        'email' => 'user@example.com',
        'scopes' => [],
        'remember_me' => true,
      ]);

    /** @var OtpRepositoryPort&MockObject $otpRepository */
    $otpRepository = $this->createMock(OtpRepositoryPort::class);
    $otpRepository->expects(self::once())
      ->method('findByChallengeToken')
      ->willReturn($otp);

    $otpChallenge = $this->createStub(OtpChallengePort::class);

    $handler = new MfaResendHandler(
      jwtService: $jwtService,
      otpRepository: $otpRepository,
      otpChallenge: $otpChallenge,
    );

    $result = $handler->__invoke(new MfaResendCommand(preAuthToken: 'pre-auth'));

    self::assertFalse($result->success);
    self::assertSame(MfaResendResult::ERROR_TOTP_NOT_RESENDABLE, $result->errorCode);
  }
}
