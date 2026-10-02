<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Application\UseCase\Command\Totp\SetupTotp;

use Otp\Application\Port\Outbound\Totp\{TotpEnrollmentRepositoryPort, TotpServicePort};
use Otp\Application\UseCase\Command\Totp\SetupTotp\{SetupTotpCommand, SetupTotpHandler, SetupTotpResult};
use Otp\Domain\Exception\TotpEnrollmentUnavailableException;
use Otp\Domain\Model\Totp\TotpEnrollment;
use Otp\Domain\ValueObject\TotpSecret;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SplQueue;
use User\Application\Port\Inbound\AccountStatusPort;

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
    $qrCodeUri = 'otpauth://totp/Fireguard%20Auth:test@example.com?secret=JBSWY3DPEHPK3PXP&issuer=Fireguard%20Auth';

    $totpService = $this->createMock(TotpServicePort::class);
    $totpService->expects(self::once())
      ->method('generateSecret')
      ->willReturn($secret);
    $totpService->expects(self::once())
      ->method('getProvisioningUri')
      ->with($secret, 'test@example.com', 'Fireguard Auth')
      ->willReturn($qrCodeUri);

    /** @var TotpEnrollmentRepositoryPort&MockObject $enrollmentRepository */
    $enrollmentRepository = $this->createMock(TotpEnrollmentRepositoryPort::class);
    $enrollmentRepository->method('withUserLock')->willReturnCallback(static fn (string $userId, callable $operation): mixed => $operation());
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

    $handler = new SetupTotpHandler(totpService: $totpService, enrollmentRepository: $enrollmentRepository, accountStatus: $this->activeAccount());

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

    /** @var TotpEnrollmentRepositoryPort&MockObject $enrollmentRepository */
    $enrollmentRepository = $this->createMock(TotpEnrollmentRepositoryPort::class);
    $enrollmentRepository->method('withUserLock')->willReturnCallback(static fn (string $userId, callable $operation): mixed => $operation());
    $enrollmentRepository->expects(self::once())
      ->method('findByUserId')
      ->willReturn($existingEnrollment);
    $enrollmentRepository->expects(self::once())
      ->method('save')
      ->with(self::callback(static function (TotpEnrollment $enrollment) use ($newSecret): bool {
        return !$enrollment->isActive()
          && $enrollment->hasPending()
          && $newSecret->secret === $enrollment->pendingSecret()?->secret;
      }));

    $handler = new SetupTotpHandler(totpService: $totpService, enrollmentRepository: $enrollmentRepository, accountStatus: $this->activeAccount());

    $handler->__invoke(new SetupTotpCommand(userId: 'user-123', accountName: 'test@example.com'));
  }

  #[Test]
  public function testActiveEnrollmentCannotStartReplacementWithoutCurrentFactorProof(): void
  {
    $enrollment = TotpEnrollment::startEnrollment('user-123', new TotpSecret('BBBBBBBBBBBBBBBB'), 5);
    $enrollment->confirmPending(true);
    $repository = $this->createStub(TotpEnrollmentRepositoryPort::class);
    $repository->method('withUserLock')->willReturnCallback(static fn (string $userId, callable $operation): mixed => $operation());
    $repository->method('findByUserId')->willReturn($enrollment);
    $service = $this->createStub(TotpServicePort::class);
    $service->method('generateSecret')->willReturn(new TotpSecret('AAAAAAAAAAAAAAAA'));
    $service->method('getProvisioningUri')->willReturn('otpauth://totp/test');
    $handler = new SetupTotpHandler($service, $repository, $this->activeAccount());
    $this->expectException(\Shared\Domain\Exception\DomainException::class);
    $handler->__invoke(new SetupTotpCommand('user-123', 'test@example.com'));
  }

  #[Test]
  public function testUnavailableAccountIsCheckedInsideTheLockBeforeGeneratingAnySecret(): void
  {
    /** @var SplQueue<string> $lockOrder */
    $lockOrder = new SplQueue();
    $status = $this->createMock(AccountStatusPort::class);
    $status->expects(self::once())->method('isActive')->with('user-123')->willReturnCallback(static function () use ($lockOrder): bool {
      self::assertSame('locked', $lockOrder->dequeue());

      return false;
    });
    $repository = $this->createMock(TotpEnrollmentRepositoryPort::class);
    $repository->method('withUserLock')->willReturnCallback(static function (string $userId, callable $operation) use ($lockOrder): mixed {
      $lockOrder->enqueue('locked');

      return $operation();
    });
    $repository->expects(self::never())->method('save');
    $service = $this->createMock(TotpServicePort::class);
    $service->expects(self::never())->method('generateSecret');
    $handler = new SetupTotpHandler($service, $repository, $status);
    $this->expectException(TotpEnrollmentUnavailableException::class);
    $handler->__invoke(new SetupTotpCommand('user-123', 'test@example.com'));
  }

  #[Test]
  public function testAccountLookupFailureNeverGeneratesOrPersistsAnEnrollment(): void
  {
    $status = $this->createStub(AccountStatusPort::class);
    $status->method('isActive')->willThrowException(new RuntimeException('Synthetic account lookup failure.'));
    $repository = $this->createMock(TotpEnrollmentRepositoryPort::class);
    $repository->method('withUserLock')->willReturnCallback(static fn (string $userId, callable $operation): mixed => $operation());
    $repository->expects(self::never())->method('save');
    $service = $this->createMock(TotpServicePort::class);
    $service->expects(self::never())->method('generateSecret');
    $handler = new SetupTotpHandler($service, $repository, $status);
    $this->expectException(RuntimeException::class);
    $handler->__invoke(new SetupTotpCommand('user-123', 'test@example.com'));
  }

  private function activeAccount(): AccountStatusPort
  {
    $status = $this->createStub(AccountStatusPort::class);
    $status->method('isActive')->willReturn(true);

    return $status;
  }
}
