<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Application\UseCase\Query\Challenge\GetChallengeStatus;

use DateTimeImmutable;
use Otp\Application\Exception\OtpNotFoundException;
use Otp\Application\Port\Outbound\Challenge\OtpRepositoryPort;
use Otp\Application\UseCase\Query\Challenge\GetChallengeStatus\{GetChallengeStatusHandler, GetChallengeStatusQuery, GetChallengeStatusResult};
use Otp\Domain\Model\Otp;
use Otp\Domain\ValueObject\{ChallengeToken, OtpChannel, OtpCode, OtpId, OtpPurpose};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test GetChallengeStatusHandlerTest.
 *
 * @category Handler Tests
 */
#[CoversClass(className: GetChallengeStatusHandler::class)]
final class GetChallengeStatusHandlerTest extends TestCase
{
  // #region Tests
  #[Test]
  public function testInvokeThrowsWhenOtpMissing(): void
  {
    $repository = $this->createMock(OtpRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findByChallengeToken')
      ->willReturn(null);

    $handler = new GetChallengeStatusHandler($repository);

    $this->expectException(OtpNotFoundException::class);

    $handler->__invoke(new GetChallengeStatusQuery('token-123'));
  }

  #[Test]
  public function testInvokeReturnsStatusForPendingOtp(): void
  {
    $otp = Otp::generate(
      id: new OtpId('123e4567-e89b-12d3-a456-426614174300'),
      userId: 'user-123',
      purpose: OtpPurpose::LOGIN,
      channel: OtpChannel::EMAIL,
      recipient: 'user@example.com',
    );

    $repository = $this->createMock(OtpRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findByChallengeToken')
      ->willReturn($otp);

    $handler = new GetChallengeStatusHandler($repository);

    $result = $handler->__invoke(new GetChallengeStatusQuery($otp->challengeToken()->value));

    self::assertInstanceOf(GetChallengeStatusResult::class, $result);
    self::assertSame('pending', $result->status);
    self::assertGreaterThan(0, $result->canResendIn);
  }

  #[Test]
  public function testInvokeReturnsZeroResendForVerifiedOtp(): void
  {
    $otp = Otp::reconstitute(
      id: new OtpId('123e4567-e89b-12d3-a456-426614174301'),
      challengeToken: ChallengeToken::fromString('token-301'),
      userId: 'user-123',
      purpose: OtpPurpose::LOGIN,
      channel: OtpChannel::EMAIL,
      codeHash: OtpCode::generate()->hash(),
      recipient: 'user@example.com',
      expiresAt: new DateTimeImmutable('+10 minutes'),
      maxAttempts: 5,
      attempts: 0,
      verifiedAt: new DateTimeImmutable('-1 minute'),
      createdAt: new DateTimeImmutable('-2 minutes'),
    );

    $repository = $this->createMock(OtpRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findByChallengeToken')
      ->willReturn($otp);

    $handler = new GetChallengeStatusHandler($repository);

    $result = $handler->__invoke(new GetChallengeStatusQuery('token-301'));

    self::assertSame('verified', $result->status);
    self::assertSame(0, $result->canResendIn);
  }
  // #endregion
}
