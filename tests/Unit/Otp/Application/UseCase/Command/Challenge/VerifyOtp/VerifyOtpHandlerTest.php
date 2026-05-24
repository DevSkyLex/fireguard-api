<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Application\UseCase\Command\Challenge\VerifyOtp;

use DateTimeImmutable;
use InvalidArgumentException;
use Otp\Application\Exception\OtpNotFoundException;
use Otp\Application\Port\Outbound\Challenge\OtpRepositoryPort;
use Otp\Application\UseCase\Command\Challenge\VerifyOtp\{VerifyOtpCommand, VerifyOtpHandler};
use Otp\Domain\Model\Otp;
use Otp\Domain\ValueObject\{ChallengeToken, OtpChannel, OtpCode, OtpId, OtpPurpose};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test VerifyOtpHandlerTest.
 *
 * @category Unit Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(VerifyOtpHandler::class)]
final class VerifyOtpHandlerTest extends TestCase
{
  #[Test]
  public function testInvokeVerifiesCorrectCode(): void
  {
    $otpId = '123e4567-e89b-12d3-a456-426614174000';

    $otp = Otp::generate(
      id: new OtpId($otpId),
      userId: 'user-123',
      purpose: OtpPurpose::LOGIN,
      channel: OtpChannel::EMAIL,
      recipient: 'test@example.com',
    );

    $code = $otp->code()->plain();

    $repository = $this->createMock(OtpRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
      ->with(self::callback(fn (OtpId $id) => $id->value === $otpId))
      ->willReturn($otp);
    $repository->expects(self::once())
      ->method('save')
      ->with($otp);

    $handler = new VerifyOtpHandler(otpRepository: $repository);

    $command = new VerifyOtpCommand(
      otpId: $otpId,
      code: $code,
    );

    $result = $handler->__invoke($command);

    self::assertTrue($result->success);
    self::assertNull($result->error);
  }

  #[Test]
  public function testInvokeRejectsIncorrectCode(): void
  {
    $otpId = '123e4567-e89b-12d3-a456-426614174000';

    $otp = Otp::generate(
      id: new OtpId($otpId),
      userId: 'user-123',
      purpose: OtpPurpose::LOGIN,
      channel: OtpChannel::EMAIL,
      recipient: 'test@example.com',
    );

    $repository = $this->createMock(OtpRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
      ->willReturn($otp);
    $repository->expects(self::once())
      ->method('save');

    $handler = new VerifyOtpHandler(otpRepository: $repository);

    $command = new VerifyOtpCommand(
      otpId: $otpId,
      code: '000000',
    );

    $result = $handler->__invoke($command);

    self::assertFalse($result->success);
    self::assertNotNull($result->error);
  }

  #[Test]
  public function testInvokeThrowsWhenOtpNotFound(): void
  {
    $repository = $this->createMock(OtpRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
      ->willReturn(null);

    $handler = new VerifyOtpHandler(otpRepository: $repository);

    $command = new VerifyOtpCommand(
      otpId: '123e4567-e89b-12d3-a456-426614174000',
      code: '123456',
    );

    $this->expectException(OtpNotFoundException::class);
    $handler->__invoke($command);
  }

  #[Test]
  public function testInvokeThrowsWhenNoIdentifiersProvided(): void
  {
    $handler = new VerifyOtpHandler(otpRepository: $this->createStub(OtpRepositoryPort::class));

    $this->expectException(InvalidArgumentException::class);

    $handler->__invoke(new VerifyOtpCommand(code: '123456'));
  }

  #[Test]
  public function testInvokeUsesChallengeToken(): void
  {
    $otp = Otp::generate(
      id: new OtpId('123e4567-e89b-12d3-a456-426614174010'),
      userId: 'user-123',
      purpose: OtpPurpose::LOGIN,
      channel: OtpChannel::EMAIL,
      recipient: 'test@example.com',
    );

    $repository = $this->createMock(OtpRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findByChallengeToken')
      ->willReturn($otp);
    $repository->expects(self::once())
      ->method('save');

    $handler = new VerifyOtpHandler(otpRepository: $repository);

    $command = new VerifyOtpCommand(
      challengeToken: $otp->challengeToken()->value,
      code: $otp->code()->plain(),
    );

    $result = $handler->__invoke($command);

    self::assertTrue($result->success);
  }

  #[Test]
  public function testInvokeReturnsExpiredError(): void
  {
    $otp = Otp::reconstitute(
      id: new OtpId('123e4567-e89b-12d3-a456-426614174011'),
      challengeToken: ChallengeToken::fromString('token-expired'),
      userId: 'user-123',
      purpose: OtpPurpose::LOGIN,
      channel: OtpChannel::EMAIL,
      codeHash: OtpCode::generate()->hash(),
      recipient: 'test@example.com',
      expiresAt: new DateTimeImmutable('-10 minutes'),
      maxAttempts: 5,
      attempts: 0,
      verifiedAt: null,
      createdAt: new DateTimeImmutable('-20 minutes'),
    );

    $repository = $this->createMock(OtpRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
      ->willReturn($otp);

    $handler = new VerifyOtpHandler(otpRepository: $repository);

    $command = new VerifyOtpCommand(
      otpId: '123e4567-e89b-12d3-a456-426614174011',
      code: '123456',
    );

    $result = $handler->__invoke($command);

    self::assertFalse($result->success);
    self::assertSame('OTP has expired.', $result->error);
  }

  #[Test]
  public function testInvokeReturnsMaxAttemptsError(): void
  {
    $otp = Otp::reconstitute(
      id: new OtpId('123e4567-e89b-12d3-a456-426614174012'),
      challengeToken: ChallengeToken::fromString('token-max'),
      userId: 'user-123',
      purpose: OtpPurpose::LOGIN,
      channel: OtpChannel::EMAIL,
      codeHash: OtpCode::generate()->hash(),
      recipient: 'test@example.com',
      expiresAt: new DateTimeImmutable('+10 minutes'),
      maxAttempts: 1,
      attempts: 1,
      verifiedAt: null,
      createdAt: new DateTimeImmutable('-20 minutes'),
    );

    $repository = $this->createMock(OtpRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
      ->willReturn($otp);

    $handler = new VerifyOtpHandler(otpRepository: $repository);

    $command = new VerifyOtpCommand(
      otpId: '123e4567-e89b-12d3-a456-426614174012',
      code: '123456',
    );

    $result = $handler->__invoke($command);

    self::assertFalse($result->success);
    self::assertSame('Maximum verification attempts exceeded.', $result->error);
  }
}
