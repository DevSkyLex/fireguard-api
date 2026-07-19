<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Application\UseCase\Command\Totp\ConfirmTotp;

use Otp\Application\Exception\TotpPendingEnrollmentNotFoundException;
use Otp\Application\Port\Outbound\Totp\{TotpEnrollmentRepositoryPort, TotpServicePort};
use Otp\Application\UseCase\Command\Totp\ConfirmTotp\{ConfirmTotpCommand, ConfirmTotpHandler};
use Otp\Domain\Event\Totp\TotpEnrollmentConfirmedEvent;
use Otp\Domain\Model\Totp\TotpEnrollment;
use Otp\Domain\ValueObject\TotpSecret;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\EventDispatcherPort;

/**
 * Test ConfirmTotpHandlerTest.
 *
 * @category Unit Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ConfirmTotpHandler::class)]
final class ConfirmTotpHandlerTest extends TestCase
{
  #[Test]
  public function testInvokeActivatesTotpOnValidCode(): void
  {
    $secret = new TotpSecret('JBSWY3DPEHPK3PXP');
    $enrollment = TotpEnrollment::startEnrollment(userId: 'user-1', secret: $secret, maxAttempts: 5);

    /** @var TotpEnrollmentRepositoryPort&MockObject $repository */
    $repository = $this->createMock(TotpEnrollmentRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findByUserId')
      ->with('user-1')
      ->willReturn($enrollment);
    $repository->expects(self::once())
      ->method('save')
      ->with($enrollment);

    /** @var TotpServicePort&MockObject $totpService */
    $totpService = $this->createMock(TotpServicePort::class);
    $totpService->expects(self::once())
      ->method('verify')
      ->with('123456', $secret)
      ->willReturn(true);

    /** @var EventDispatcherPort&MockObject $dispatcher */
    $dispatcher = $this->createMock(EventDispatcherPort::class);
    $dispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::isInstanceOf(TotpEnrollmentConfirmedEvent::class));

    $handler = new ConfirmTotpHandler(
      enrollmentRepository: $repository,
      totpService: $totpService,
      eventDispatcher: $dispatcher,
    );

    $result = $handler->__invoke(new ConfirmTotpCommand(userId: 'user-1', code: '123456'));

    self::assertTrue($result->success);
    self::assertTrue($enrollment->isActive());
  }

  #[Test]
  public function testInvokeFailsOnInvalidCode(): void
  {
    $secret = new TotpSecret('JBSWY3DPEHPK3PXP');
    $enrollment = TotpEnrollment::startEnrollment(userId: 'user-1', secret: $secret, maxAttempts: 5);

    /** @var TotpEnrollmentRepositoryPort&MockObject $repository */
    $repository = $this->createMock(TotpEnrollmentRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findByUserId')
      ->willReturn($enrollment);
    $repository->expects(self::once())
      ->method('save');

    /** @var TotpServicePort&MockObject $totpService */
    $totpService = $this->createMock(TotpServicePort::class);
    $totpService->expects(self::once())
      ->method('verify')
      ->willReturn(false);

    /** @var EventDispatcherPort&MockObject $dispatcher */
    $dispatcher = $this->createMock(EventDispatcherPort::class);
    $dispatcher->expects(self::never())->method('dispatch');

    $handler = new ConfirmTotpHandler(
      enrollmentRepository: $repository,
      totpService: $totpService,
      eventDispatcher: $dispatcher,
    );

    $result = $handler->__invoke(new ConfirmTotpCommand(userId: 'user-1', code: '000000'));

    self::assertFalse($result->success);
    self::assertSame(4, $result->attemptsRemaining);
    self::assertNotNull($result->error);
  }

  #[Test]
  public function testInvokeThrowsWhenNoPendingEnrollment(): void
  {
    /** @var TotpEnrollmentRepositoryPort&MockObject $repository */
    $repository = $this->createMock(TotpEnrollmentRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findByUserId')
      ->willReturn(null);

    $handler = new ConfirmTotpHandler(
      enrollmentRepository: $repository,
      totpService: $this->createStub(TotpServicePort::class),
      eventDispatcher: $this->createStub(EventDispatcherPort::class),
    );

    $this->expectException(TotpPendingEnrollmentNotFoundException::class);

    $handler->__invoke(new ConfirmTotpCommand(userId: 'user-1', code: '123456'));
  }

  #[Test]
  public function testInvokeReturnsFailureWhenAttemptsExhausted(): void
  {
    $enrollment = TotpEnrollment::startEnrollment(
      userId: 'user-1',
      secret: new TotpSecret('JBSWY3DPEHPK3PXP'),
      maxAttempts: 1,
    );
    $enrollment->confirmPending(false);

    /** @var TotpEnrollmentRepositoryPort&MockObject $repository */
    $repository = $this->createMock(TotpEnrollmentRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findByUserId')
      ->willReturn($enrollment);
    $repository->expects(self::once())
      ->method('save');

    $totpService = $this->createStub(TotpServicePort::class);
    $totpService->method('verify')->willReturn(false);

    $handler = new ConfirmTotpHandler(
      enrollmentRepository: $repository,
      totpService: $totpService,
      eventDispatcher: $this->createStub(EventDispatcherPort::class),
    );

    $result = $handler->__invoke(new ConfirmTotpCommand(userId: 'user-1', code: '000000'));

    self::assertFalse($result->success);
    self::assertSame(0, $result->attemptsRemaining);
  }
}
