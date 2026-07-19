<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Application\UseCase\Command\Totp\SetupTotp;

use Otp\Application\Port\Outbound\Totp\{TotpEnrollmentRepositoryPort, TotpServicePort};
use Otp\Application\UseCase\Command\Totp\SetupTotp\{SetupTotpCommand, SetupTotpHandler, SetupTotpResult};
use Otp\Domain\Model\Totp\TotpEnrollment;
use Otp\Domain\ValueObject\TotpSecret;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test SetupTotpHandlerTest.
 *
 * @category Unit Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(SetupTotpHandler::class)]
final class SetupTotpHandlerTest extends TestCase
{
  #[Test]
  public function testInvokeGeneratesTotpSecretAndStartsNewPendingEnrollment(): void
  {
    $secret = new TotpSecret('JBSWY3DPEHPK3PXP');
    $qrCodeUri = 'otpauth://totp/FireGuard%20Auth:test@example.com?secret=JBSWY3DPEHPK3PXP&issuer=FireGuard%20Auth';

    $totpService = $this->createMock(TotpServicePort::class);
    $totpService->expects(self::once())
      ->method('generateSecret')
      ->willReturn($secret);
    $totpService->expects(self::once())
      ->method('getProvisioningUri')
      ->with($secret, 'test@example.com', 'FireGuard Auth')
      ->willReturn($qrCodeUri);

    /** @var TotpEnrollmentRepositoryPort&MockObject $enrollmentRepository */
    $enrollmentRepository = $this->createMock(TotpEnrollmentRepositoryPort::class);
    $enrollmentRepository->expects(self::once())
      ->method('findByUserId')
      ->with('user-123')
      ->willReturn(null);
    $enrollmentRepository->expects(self::once())
      ->method('save')
      ->with(self::callback(static function (TotpEnrollment $enrollment) use ($secret): bool {
        return 'user-123' === $enrollment->userId()
          && $enrollment->hasPending()
          && !$enrollment->isActive()
          && $secret->secret === $enrollment->pendingSecret()?->secret;
      }));

    $handler = new SetupTotpHandler(totpService: $totpService, enrollmentRepository: $enrollmentRepository);

    $command = new SetupTotpCommand(
      userId: 'user-123',
      accountName: 'test@example.com',
    );

    $result = $handler->__invoke($command);

    self::assertInstanceOf(SetupTotpResult::class, $result);
    self::assertEquals('JBSWY3DPEHPK3PXP', $result->secret);
    self::assertEquals($qrCodeUri, $result->qrCodeUri);
  }

  #[Test]
  public function testInvokeReplacesPendingSecretOnExistingEnrollment(): void
  {
    $newSecret = new TotpSecret('AAAAAAAAAAAAAAAA');

    $totpService = $this->createMock(TotpServicePort::class);
    $totpService->expects(self::once())
      ->method('generateSecret')
      ->willReturn($newSecret);
    $totpService->expects(self::once())
      ->method('getProvisioningUri')
      ->willReturn('otpauth://totp/example');

    $existingEnrollment = TotpEnrollment::startEnrollment(
      userId: 'user-123',
      secret: new TotpSecret('BBBBBBBBBBBBBBBB'),
      maxAttempts: 5,
    );
    // Simulate a previously confirmed active secret that must survive re-setup.
    $existingEnrollment->confirmPending(true);

    /** @var TotpEnrollmentRepositoryPort&MockObject $enrollmentRepository */
    $enrollmentRepository = $this->createMock(TotpEnrollmentRepositoryPort::class);
    $enrollmentRepository->expects(self::once())
      ->method('findByUserId')
      ->willReturn($existingEnrollment);
    $enrollmentRepository->expects(self::once())
      ->method('save')
      ->with(self::callback(static function (TotpEnrollment $enrollment) use ($newSecret): bool {
        return $enrollment->isActive()
          && $enrollment->hasPending()
          && $newSecret->secret === $enrollment->pendingSecret()?->secret;
      }));

    $handler = new SetupTotpHandler(totpService: $totpService, enrollmentRepository: $enrollmentRepository);

    $handler->__invoke(new SetupTotpCommand(userId: 'user-123', accountName: 'test@example.com'));
  }
}
