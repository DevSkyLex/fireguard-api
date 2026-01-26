<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Application\UseCase\Command\Challenge\ResendChallenge;

use DateTimeImmutable;
use Otp\Application\Exception\{OtpNotFoundException, ResendNotAllowedException};
use Otp\Application\Port\Outbound\Challenge\{OtpNotifierPort, OtpRepositoryPort};
use Otp\Application\UseCase\Command\Challenge\GenerateOtp\GenerateOtpHandler;
use Otp\Application\UseCase\Command\Challenge\ResendChallenge\{ResendChallengeCommand, ResendChallengeHandler, ResendChallengeResult};
use Otp\Domain\Model\Otp;
use Otp\Domain\ValueObject\{ChallengeToken, OtpChannel, OtpCode, OtpId, OtpPurpose};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\UuidGeneratorPort;

/**
 * Test ResendChallengeHandlerTest.
 *
 * @category Handler Tests
 */
#[CoversClass(className: ResendChallengeHandler::class)]
final class ResendChallengeHandlerTest extends TestCase
{
  // #region Tests
  #[Test]
  public function testInvokeThrowsWhenOtpMissing(): void
  {
    $otpRepository = $this->createMock(OtpRepositoryPort::class);
    $otpRepository->expects(self::once())
      ->method('findByChallengeToken')
      ->willReturn(null);

    $handler = new ResendChallengeHandler(
      otpRepository: $otpRepository,
      generateHandler: $this->createGenerateHandler(),
    );

    $this->expectException(OtpNotFoundException::class);

    $handler->__invoke(new ResendChallengeCommand(
      challengeToken: 'token-123',
      userId: 'user-123',
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenResendNotAllowed(): void
  {
    $otp = Otp::generate(
      id: new OtpId('123e4567-e89b-12d3-a456-426614174100'),
      userId: 'user-123',
      purpose: OtpPurpose::LOGIN,
      channel: OtpChannel::EMAIL,
      recipient: 'user@example.com',
    );

    $otpRepository = $this->createMock(OtpRepositoryPort::class);
    $otpRepository->expects(self::once())
      ->method('findByChallengeToken')
      ->willReturn($otp);

    $handler = new ResendChallengeHandler(
      otpRepository: $otpRepository,
      generateHandler: $this->createGenerateHandler(),
    );

    $this->expectException(ResendNotAllowedException::class);

    $handler->__invoke(new ResendChallengeCommand(
      challengeToken: $otp->challengeToken()->value,
      userId: 'user-123',
    ));
  }

  #[Test]
  public function testInvokeReturnsResendResult(): void
  {
    $otp = Otp::reconstitute(
      id: new OtpId('123e4567-e89b-12d3-a456-426614174200'),
      challengeToken: ChallengeToken::fromString('token-200'),
      userId: 'user-200',
      purpose: OtpPurpose::LOGIN,
      channel: OtpChannel::TOTP,
      codeHash: OtpCode::generate()->hash(),
      recipient: 'user@example.com',
      expiresAt: new DateTimeImmutable('+10 minutes'),
      maxAttempts: 5,
      attempts: 0,
      verifiedAt: null,
      createdAt: new DateTimeImmutable('-2 minutes'),
    );

    $otpRepository = $this->createMock(OtpRepositoryPort::class);
    $otpRepository->expects(self::once())
      ->method('findByChallengeToken')
      ->willReturn($otp);

    $handler = new ResendChallengeHandler(
      otpRepository: $otpRepository,
      generateHandler: $this->createGenerateHandler(),
    );

    $result = $handler->__invoke(new ResendChallengeCommand(
      challengeToken: 'token-200',
      userId: 'user-200',
    ));

    self::assertInstanceOf(ResendChallengeResult::class, $result);
    self::assertSame('login', $result->purpose);
    self::assertSame('totp', $result->channel);
    self::assertSame(5, $result->maxAttempts);
  }
  // #endregion

  // #region Helpers
  private function createGenerateHandler(): GenerateOtpHandler
  {
    $otpRepository = $this->createMock(OtpRepositoryPort::class);
    $otpRepository->method('revokeAllForUser');
    $otpRepository->method('save');

    $notifier = $this->createMock(OtpNotifierPort::class);

    $uuidGenerator = $this->createMock(UuidGeneratorPort::class);
    $uuidGenerator->method('generate')
      ->willReturn('123e4567-e89b-12d3-a456-426614174999');

    $uuidFactory = new UuidFactory($uuidGenerator);

    return new GenerateOtpHandler(
      otpRepository: $otpRepository,
      otpNotifier: $notifier,
      uuidFactory: $uuidFactory,
    );
  }
  // #endregion
}
