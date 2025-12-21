<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Application\UseCase\Command\VerifyOtp;

use Otp\Application\Port\Outbound\OtpRepositoryPort;
use Otp\Application\UseCase\Command\VerifyOtp\VerifyOtpCommand;
use Otp\Application\UseCase\Command\VerifyOtp\VerifyOtpHandler;
use Otp\Domain\Exception\OtpNotFoundException;
use Otp\Domain\Model\Otp;
use Otp\Domain\ValueObject\OtpChannel;
use Otp\Domain\ValueObject\OtpId;
use Otp\Domain\ValueObject\OtpPurpose;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
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
}
