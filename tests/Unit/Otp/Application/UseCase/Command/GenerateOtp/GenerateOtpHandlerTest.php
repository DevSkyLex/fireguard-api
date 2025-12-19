<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Application\UseCase\Command\GenerateOtp;

use Otp\Application\Port\Outbound\OtpNotifierPort;
use Otp\Application\Port\Outbound\OtpRepositoryPort;
use Otp\Application\UseCase\Command\GenerateOtp\GenerateOtpCommand;
use Otp\Application\UseCase\Command\GenerateOtp\GenerateOtpHandler;
use Otp\Application\UseCase\Command\GenerateOtp\GenerateOtpResult;
use Otp\Domain\Model\Otp;
use Otp\Domain\ValueObject\OtpChannel;
use Otp\Domain\ValueObject\OtpId;
use Otp\Domain\ValueObject\OtpPurpose;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Application\Factory\UuidFactory;

/**
 * Test GenerateOtpHandlerTest.
 *
 * @category Unit Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GenerateOtpHandler::class)]
final class GenerateOtpHandlerTest extends TestCase
{
    #[Test]
    public function testInvokeGeneratesOtpAndSendsNotification(): void
    {
        $otpId = '123e4567-e89b-12d3-a456-426614174000';

        $repository = $this->createMock(OtpRepositoryPort::class);
        $repository->expects(self::once())
          ->method('revokeAllForUser')
          ->with('user-123', OtpPurpose::LOGIN);
        $repository->expects(self::once())
          ->method('save')
          ->with(self::isInstanceOf(Otp::class));

        $notifier = $this->createMock(OtpNotifierPort::class);
        $notifier->expects(self::once())
          ->method('send')
          ->with(self::isInstanceOf(Otp::class));

        $uuidFactory = $this->createMock(UuidFactory::class);
        $uuidFactory->expects(self::once())
          ->method('create')
          ->with(OtpId::class)
          ->willReturn(new OtpId($otpId));

        $handler = new GenerateOtpHandler(
            otpRepository: $repository,
            otpNotifier: $notifier,
            uuidFactory: $uuidFactory,
        );

        $command = new GenerateOtpCommand(
            userId: 'user-123',
            purpose: OtpPurpose::LOGIN,
            channel: OtpChannel::EMAIL,
            recipient: 'test@example.com',
        );

        $result = $handler->__invoke($command);

        self::assertInstanceOf(GenerateOtpResult::class, $result);
        self::assertEquals($otpId, $result->otpId);
        self::assertStringContainsString('@example.com', $result->maskedRecipient);
    }

    #[Test]
    public function testInvokeDoesNotSendNotificationForTotp(): void
    {
        $otpId = '123e4567-e89b-12d3-a456-426614174000';

        $repository = $this->createMock(OtpRepositoryPort::class);

        $notifier = $this->createMock(OtpNotifierPort::class);
        $notifier->expects(self::never())->method('send');

        $uuidFactory = $this->createMock(UuidFactory::class);
        $uuidFactory->expects(self::once())
          ->method('create')
          ->willReturn(new OtpId($otpId));

        $handler = new GenerateOtpHandler(
            otpRepository: $repository,
            otpNotifier: $notifier,
            uuidFactory: $uuidFactory,
        );

        $command = new GenerateOtpCommand(
            userId: 'user-123',
            purpose: OtpPurpose::LOGIN,
            channel: OtpChannel::TOTP,
            recipient: 'authenticator',
        );

        $result = $handler->__invoke($command);

        self::assertEquals('Authenticator App', $result->maskedRecipient);
    }
}
