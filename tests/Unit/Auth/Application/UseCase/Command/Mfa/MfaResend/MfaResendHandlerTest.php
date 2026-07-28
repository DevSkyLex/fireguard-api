<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Application\UseCase\Command\Mfa\MfaResend;

use Auth\Application\Port\Outbound\JwtTokenServicePort;
use Auth\Application\UseCase\Command\Mfa\MfaResend\{MfaResendCommand, MfaResendHandler, MfaResendResult};
use Otp\Application\Port\Inbound\Challenge\OtpChallengePort;
use Otp\Application\Port\Outbound\Challenge\OtpRepositoryPort;
use Otp\Domain\Model\Otp;
use Otp\Domain\ValueObject\{OtpChannel, OtpId, OtpPurpose};
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
