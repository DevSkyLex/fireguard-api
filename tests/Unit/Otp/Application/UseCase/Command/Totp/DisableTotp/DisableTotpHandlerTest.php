<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Application\UseCase\Command\Totp\DisableTotp;

use DateTimeImmutable;
use Otp\Application\Exception\TotpEnrollmentNotEnabledException;
use Otp\Application\Port\Outbound\Internal\Totp\{TotpEnrollmentRepositoryPort, TotpServicePort};
use Otp\Application\UseCase\Command\Totp\DisableTotp\{DisableTotpCommand, DisableTotpHandler};
use Otp\Domain\Event\Totp\TotpEnrollmentDisabledEvent;
use Otp\Domain\Model\Totp\TotpEnrollment;
use Otp\Domain\ValueObject\TotpSecret;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\EventDispatcherPort;
use User\Application\Port\Inbound\AccountStatusPort;

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
    $repository->method('withUserLock')->willReturnCallback(static fn (string $userId, callable $operation): mixed => $operation());
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
      accountStatus: $this->accounts(),
      securityRevision: $this->createStub(\User\Application\Port\Inbound\UserSecurityRevisionPort::class),
      rootTransaction: new \Tests\Support\Shared\ImmediateRootTransactionManager(),
      durableEvents: $this->createStub(EventDispatcherPort::class),
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
    $repository->method('withUserLock')->willReturnCallback(static fn (string $userId, callable $operation): mixed => $operation());
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
      accountStatus: $this->accounts(),
      securityRevision: $this->createStub(\User\Application\Port\Inbound\UserSecurityRevisionPort::class),
      rootTransaction: new \Tests\Support\Shared\ImmediateRootTransactionManager(),
      durableEvents: $this->createStub(EventDispatcherPort::class),
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
    $repository->method('withUserLock')->willReturnCallback(static fn (string $userId, callable $operation): mixed => $operation());
    $repository->expects(self::once())
      ->method('findByUserId')
      ->willReturn(null);

    $handler = new DisableTotpHandler(
      enrollmentRepository: $repository,
      totpService: $this->createStub(TotpServicePort::class),
      eventDispatcher: $this->createStub(EventDispatcherPort::class),
      accountStatus: $this->accounts(),
      securityRevision: $this->createStub(\User\Application\Port\Inbound\UserSecurityRevisionPort::class),
      rootTransaction: new \Tests\Support\Shared\ImmediateRootTransactionManager(),
      durableEvents: $this->createStub(EventDispatcherPort::class),
    );

    $this->expectException(TotpEnrollmentNotEnabledException::class);

    $handler->__invoke(new DisableTotpCommand(userId: 'user-1', code: '123456'));
  }

  public function testLoginConsumedCodeCannotDisableAndIndependentCodeClearsRecoveryLot(): void
  {
    $first = 'abcd-abcd-abcd-abcd-abcd-abcd-abcd-abcd';
    $second = '1234-1234-1234-1234-1234-1234-1234-1234';
    $enrollment = TotpEnrollment::startEnrollment('user-1', new TotpSecret('JBSWY3DPEHPK3PXP'), 5);
    $enrollment->confirmPending(true);
    $hashes = [\Otp\Domain\ValueObject\RecoveryCode::hash($first), \Otp\Domain\ValueObject\RecoveryCode::hash($second)];
    self::assertIsString($hashes[0]);
    self::assertIsString($hashes[1]);
    $enrollment->replaceRecoveryCodes($hashes, new DateTimeImmutable());
    self::assertTrue($enrollment->consumeRecoveryCode($first));
    $repo = $this->createMock(TotpEnrollmentRepositoryPort::class);
    $repo->method('withUserLock')->willReturnCallback(static fn (string $user, callable $operation): mixed => $operation());
    $repo->method('findByUserId')->willReturn($enrollment);
    $repo->expects(self::exactly(2))->method('save')->with($enrollment);
    $totp = $this->createMock(TotpServicePort::class);
    $totp->expects(self::never())->method('verify');
    $revision = $this->createMock(\User\Application\Port\Inbound\UserSecurityRevisionPort::class);
    $revision->expects(self::once())->method('advance')->with('user-1')->willReturn(1);
    $events = $this->createMock(EventDispatcherPort::class);
    $events->expects(self::once())->method('dispatch')->with(self::isInstanceOf(TotpEnrollmentDisabledEvent::class));
    $handler = new DisableTotpHandler($repo, $totp, $events, $this->accounts(), $revision, rootTransaction: new \Tests\Support\Shared\ImmediateRootTransactionManager(), durableEvents: $this->createStub(EventDispatcherPort::class));
    self::assertFalse($handler(new DisableTotpCommand('user-1', $first, 'recovery_code'))->success);
    self::assertTrue($enrollment->isActive());
    self::assertTrue($handler(new DisableTotpCommand('user-1', $second, 'recovery_code'))->success);
    self::assertFalse($enrollment->isActive());
    self::assertSame([], $enrollment->recoveryCodeHashes());
    self::assertNull($enrollment->recoveryCodesGeneratedAt());
  }

  private function accounts(): AccountStatusPort
  {
    $accounts = $this->createStub(AccountStatusPort::class);
    $accounts->method('isActive')->willReturn(true);

    return $accounts;
  }
}
