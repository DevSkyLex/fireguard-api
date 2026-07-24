<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Application\UseCase\Command\Totp\DisableTotp;

use Otp\Application\Exception\TotpEnrollmentNotEnabledException;
use Otp\Application\Port\Outbound\Totp\{TotpEnrollmentRepositoryPort, TotpServicePort};
use Otp\Application\UseCase\Command\Totp\DisableTotp\{DisableTotpCommand, DisableTotpHandler};
use Otp\Domain\Event\Totp\TotpEnrollmentDisabledEvent;
use Otp\Domain\Model\Totp\TotpEnrollment;
use Otp\Domain\ValueObject\TotpSecret;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\EventDispatcherPort;

/**
 * Test DisableTotpHandlerTest.
 *
 * @category Unit Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(DisableTotpHandler::class)]
final class DisableTotpHandlerTest extends TestCase
{
  #[Test]
  public function testInvokeDisablesTotpOnValidCode(): void
  {
    $secret = new TotpSecret('JBSWY3DPEHPK3PXP');
    $enrollment = TotpEnrollment::startEnrollment(userId: 'user-1', secret: $secret, maxAttempts: 5);
    $enrollment->confirmPending(true);

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
      ->with(self::isInstanceOf(TotpEnrollmentDisabledEvent::class));

    $handler = new DisableTotpHandler(
      enrollmentRepository: $repository,
      totpService: $totpService,
      eventDispatcher: $dispatcher,
    );

    $result = $handler->__invoke(new DisableTotpCommand(userId: 'user-1', code: '123456'));

    self::assertTrue($result->success);
    self::assertFalse($enrollment->isActive());
  }

  #[Test]
  public function testInvokeFailsOnInvalidCode(): void
  {
    $secret = new TotpSecret('JBSWY3DPEHPK3PXP');
    $enrollment = TotpEnrollment::startEnrollment(userId: 'user-1', secret: $secret, maxAttempts: 5);
    $enrollment->confirmPending(true);

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

    $handler = new DisableTotpHandler(
      enrollmentRepository: $repository,
      totpService: $totpService,
      eventDispatcher: $dispatcher,
    );

    $result = $handler->__invoke(new DisableTotpCommand(userId: 'user-1', code: '000000'));

    self::assertFalse($result->success);
    self::assertTrue($enrollment->isActive());
  }

  #[Test]
  public function testInvokeThrowsWhenNotEnabled(): void
  {
    /** @var TotpEnrollmentRepositoryPort&MockObject $repository */
    $repository = $this->createMock(TotpEnrollmentRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findByUserId')
      ->willReturn(null);

    $handler = new DisableTotpHandler(
      enrollmentRepository: $repository,
      totpService: $this->createStub(TotpServicePort::class),
      eventDispatcher: $this->createStub(EventDispatcherPort::class),
    );

    $this->expectException(TotpEnrollmentNotEnabledException::class);

    $handler->__invoke(new DisableTotpCommand(userId: 'user-1', code: '123456'));
  }
}
