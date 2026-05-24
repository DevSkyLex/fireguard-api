<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Application\Service;

use DateTimeImmutable;
use Otp\Application\Contract\Challenge\{ChallengeInfo, OtpChannel, OtpPurpose, VerificationInfo};
use Otp\Application\Port\Outbound\Challenge\{OtpNotifierPort, OtpRepositoryPort};
use Otp\Application\Service\OtpChallengeService;
use Otp\Application\UseCase\Command\Challenge\GenerateOtp\GenerateOtpHandler;
use Otp\Application\UseCase\Command\Challenge\VerifyOtp\VerifyOtpHandler;
use Otp\Domain\Model\Otp;
use Otp\Domain\ValueObject\{ChallengeToken, OtpId, OtpPurpose as DomainOtpPurpose};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Factory\UuidFactory;

/**
 * Test OtpChallengeServiceTest.
 *
 * @category Service Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(OtpChallengeService::class)]
final class OtpChallengeServiceTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testGenerateReturnsChallengeInfo(): void
  {
    $otpId = new OtpId('550e8400-e29b-41d4-a716-446655440000');

    /** @var OtpRepositoryPort&MockObject $repository */
    $repository = $this->createMock(OtpRepositoryPort::class);
    $repository->expects(self::once())
      ->method('revokeAllForUser')
      ->with('user-1', DomainOtpPurpose::LOGIN)
      ->willReturn(0);
    $repository->expects(self::once())
      ->method('save')
      ->with(self::isInstanceOf(Otp::class));

    /** @var OtpNotifierPort&MockObject $notifier */
    $notifier = $this->createMock(OtpNotifierPort::class);
    $notifier->expects(self::once())
      ->method('send')
      ->with(self::isInstanceOf(Otp::class));

    /** @var UuidFactory&MockObject $uuidFactory */
    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::once())
      ->method('create')
      ->with(OtpId::class)
      ->willReturn($otpId);

    $generateHandler = new GenerateOtpHandler(
      otpRepository: $repository,
      otpNotifier: $notifier,
      uuidFactory: $uuidFactory,
    );

    $verifyHandler = new VerifyOtpHandler($this->createStub(OtpRepositoryPort::class));

    $service = new OtpChallengeService(
      generateHandler: $generateHandler,
      verifyHandler: $verifyHandler,
    );

    $result = $service->generate(
      userId: 'user-1',
      purpose: OtpPurpose::LOGIN,
      channel: OtpChannel::EMAIL,
      recipient: 'john.doe@example.com',
    );

    self::assertInstanceOf(ChallengeInfo::class, $result);
    self::assertNotSame('', $result->challengeToken);
    self::assertSame(5, $result->maxAttempts);
    self::assertInstanceOf(DateTimeImmutable::class, $result->expiresAt);
  }

  #[Test]
  public function testVerifyReturnsFailureWhenNotFound(): void
  {
    /** @var OtpRepositoryPort&MockObject $repository */
    $repository = $this->createMock(OtpRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findByChallengeToken')
      ->with(self::isInstanceOf(ChallengeToken::class))
      ->willReturn(null);

    $verifyHandler = new VerifyOtpHandler($repository);
    $generateHandler = new GenerateOtpHandler(
      otpRepository: $this->createStub(OtpRepositoryPort::class),
      otpNotifier: $this->createStub(OtpNotifierPort::class),
      uuidFactory: $this->createStub(UuidFactory::class),
    );

    $service = new OtpChallengeService(
      generateHandler: $generateHandler,
      verifyHandler: $verifyHandler,
    );

    $result = $service->verify('token-1', '123456');

    self::assertInstanceOf(VerificationInfo::class, $result);
    self::assertFalse($result->success);
    self::assertSame('Challenge not found.', $result->error);
    self::assertSame(0, $result->attemptsRemaining);
  }

  #[Test]
  public function testVerifyReturnsSuccess(): void
  {
    $otp = Otp::generate(
      id: new OtpId('550e8400-e29b-41d4-a716-446655440010'),
      userId: 'user-2',
      purpose: DomainOtpPurpose::LOGIN,
      channel: \Otp\Domain\ValueObject\OtpChannel::EMAIL,
      recipient: 'user@example.com',
    );

    $code = $otp->code()->plain();
    $token = $otp->challengeToken()->value;

    /** @var OtpRepositoryPort&MockObject $repository */
    $repository = $this->createMock(OtpRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findByChallengeToken')
      ->with(self::callback(fn (ChallengeToken $challenge) => $challenge->value === $token))
      ->willReturn($otp);
    $repository->expects(self::once())
      ->method('save')
      ->with($otp);

    $verifyHandler = new VerifyOtpHandler($repository);
    $generateHandler = new GenerateOtpHandler(
      otpRepository: $this->createStub(OtpRepositoryPort::class),
      otpNotifier: $this->createStub(OtpNotifierPort::class),
      uuidFactory: $this->createStub(UuidFactory::class),
    );

    $service = new OtpChallengeService(
      generateHandler: $generateHandler,
      verifyHandler: $verifyHandler,
    );

    $result = $service->verify($token, $code);

    self::assertTrue($result->success);
    self::assertSame(0, $result->attemptsRemaining);
    self::assertNull($result->error);
  }
  // #endregion
}
