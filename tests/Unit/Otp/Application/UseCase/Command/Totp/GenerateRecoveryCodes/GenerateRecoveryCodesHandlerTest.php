<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Application\UseCase\Command\Totp\GenerateRecoveryCodes;

use Otp\Application\Exception\{RecoveryCodesProofRejectedException, TotpEnrollmentNotEnabledException};
use Otp\Application\Port\Outbound\Internal\Totp\{RecoveryCodeGeneratorPort, TotpEnrollmentRepositoryPort, TotpServicePort};
use Otp\Application\UseCase\Command\Totp\GenerateRecoveryCodes\{GenerateRecoveryCodesCommand, GenerateRecoveryCodesHandler};
use Otp\Domain\Exception\TotpEnrollmentUnavailableException;
use Otp\Domain\Model\Totp\TotpEnrollment;
use Otp\Domain\ValueObject\TotpSecret;
use Otp\Infrastructure\Adapter\Crypto\RecoveryCodeGeneratorAdapter;
use PHPUnit\Framework\TestCase;
use Throwable;
use User\Application\Port\Inbound\AccountStatusPort;

use function array_unique;

/**
 * Class GenerateRecoveryCodesHandlerTest
 *
 * Covers current-factor generation and missing-factor, invalid-proof and inactive-account denial.
 *
 * @category Test
 */
final class GenerateRecoveryCodesHandlerTest extends TestCase
{
  public function testGeneratesTenDistinctCodesAndPersistsOnlyDigestsUnderLock(): void
  {
    $enrollment = self::active();
    $trace = [];
    $repository = $this->createMock(TotpEnrollmentRepositoryPort::class);
    $repository->expects(self::once())->method('withUserLock')->with('user', self::isCallable())
      ->willReturnCallback(static function (string $user, callable $operation) use (&$trace): mixed {
        $trace[] = 'lock';
        $result = $operation();
        $trace[] = 'unlock';

        return $result;
      });
    $repository->expects(self::once())->method('findByUserId')->willReturnCallback(static function () use (&$trace, $enrollment): TotpEnrollment {
      $trace[] = 'read';

      return $enrollment;
    });
    $repository->expects(self::once())->method('save')->with($enrollment);
    $totp = $this->createMock(TotpServicePort::class);
    $totp->expects(self::once())->method('verify')->with('123456', $enrollment->activeSecret())->willReturn(true);
    $accounts = $this->createStub(AccountStatusPort::class);
    $accounts->method('isActive')->willReturn(true);
    $revision = $this->createMock(\User\Application\Port\Inbound\UserSecurityRevisionPort::class);
    $revision->expects(self::once())->method('advance')->with('user')->willReturn(1);
    $events = $this->createMock(\Shared\Application\Port\Outbound\EventDispatcherPort::class);
    $events->expects(self::once())->method('dispatch')->with(self::callback(static function (object $event) use (&$trace): bool {
      $trace[] = 'event';

      return $event instanceof \Otp\Application\Contract\Event\Totp\RecoveryCodesGeneratedEvent && 'user' === $event->userId && 10 === $event->count && !$event->regenerated;
    }));
    $result = new GenerateRecoveryCodesHandler($repository, $totp, new RecoveryCodeGeneratorAdapter(), $accounts, $revision, $events, new \Tests\Support\Shared\ImmediateRootTransactionManager())(new GenerateRecoveryCodesCommand('user', '123456'));
    self::assertSame(['lock', 'read', 'event', 'unlock'], $trace);
    self::assertCount(10, $result->codes);
    self::assertCount(10, array_unique($result->codes));
    self::assertSame(10, $enrollment->recoveryCodesRemaining());
    self::assertSame($result->generatedAt, $enrollment->recoveryCodesGeneratedAt());
    foreach ($result->codes as $code) {
      self::assertMatchesRegularExpression('/\A[a-f0-9]{4}(?:-[a-f0-9]{4}){7}\z/', $code);
      self::assertNotContains($code, $enrollment->recoveryCodeHashes());
    }
  }

  public function testWrongProofCannotReplaceRecoveryCodes(): void
  {
    $this->denied(self::active(), true, false, RecoveryCodesProofRejectedException::class);
  }

  public function testMissingEnrollmentCannotGenerate(): void
  {
    $this->denied(null, true, false, TotpEnrollmentNotEnabledException::class);
  }

  public function testInactiveAccountCannotGenerate(): void
  {
    $this->denied(self::active(), false, false, TotpEnrollmentUnavailableException::class);
  }

  /**
   * @param class-string<Throwable> $exception expected denial
   */
  private function denied(?TotpEnrollment $enrollment, bool $active, bool $valid, string $exception): void
  {
    $repository = $this->createMock(TotpEnrollmentRepositoryPort::class);
    $repository->method('withUserLock')->willReturnCallback(static fn (string $user, callable $operation): mixed => $operation());
    $repository->method('findByUserId')->willReturn($enrollment);
    $repository->expects(self::never())->method('save');
    $totp = $this->createStub(TotpServicePort::class);
    $totp->method('verify')->willReturn($valid);
    $generator = $this->createMock(RecoveryCodeGeneratorPort::class);
    $generator->expects(self::never())->method('generate');
    $accounts = $this->createStub(AccountStatusPort::class);
    $accounts->method('isActive')->willReturn($active);
    $this->expectException($exception);
    $revision = $this->createMock(\User\Application\Port\Inbound\UserSecurityRevisionPort::class);
    $revision->expects(self::never())->method('advance');
    $events = $this->createMock(\Shared\Application\Port\Outbound\EventDispatcherPort::class);
    $events->expects(self::never())->method('dispatch');
    new GenerateRecoveryCodesHandler($repository, $totp, $generator, $accounts, $revision, $events, new \Tests\Support\Shared\ImmediateRootTransactionManager())(new GenerateRecoveryCodesCommand('user', '123456'));
  }

  private static function active(): TotpEnrollment
  {
    $enrollment = TotpEnrollment::startEnrollment('user', new TotpSecret('JBSWY3DPEHPK3PXP'), 5);
    $enrollment->confirmPending(true);

    return $enrollment;
  }
}
