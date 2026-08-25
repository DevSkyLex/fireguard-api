<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Domain\Model\Totp;

use DateInterval;
use DateTimeImmutable;
use Otp\Domain\Exception\{TotpDisableTemporarilyLockedException, TotpEnrollmentMaxAttemptsException, TotpEnrollmentNoPendingSecretException, TotpEnrollmentNotActiveException};
use Otp\Domain\Model\Totp\TotpEnrollment;
use Otp\Domain\ValueObject\TotpSecret;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test TotpEnrollmentTest.
 *
 * @category Unit Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(TotpEnrollment::class)]
final class TotpEnrollmentTest extends TestCase
{
  #[Test]
  public function testStartEnrollmentCreatesPendingOnlyState(): void
  {
    $secret = new TotpSecret('JBSWY3DPEHPK3PXP');
    $enrollment = TotpEnrollment::startEnrollment(userId: 'user-1', secret: $secret, maxAttempts: 5);

    self::assertSame('user-1', $enrollment->userId());
    self::assertTrue($enrollment->hasPending());
    self::assertFalse($enrollment->isActive());
    self::assertSame($secret, $enrollment->pendingSecret());
    self::assertNull($enrollment->activeSecret());
    self::assertSame(0, $enrollment->attempts());
    self::assertSame(5, $enrollment->maxAttempts());
    self::assertSame(5, $enrollment->attemptsRemaining());
  }

  #[Test]
  public function testConfirmPendingActivatesOnValidCode(): void
  {
    $secret = new TotpSecret('JBSWY3DPEHPK3PXP');
    $enrollment = TotpEnrollment::startEnrollment(userId: 'user-1', secret: $secret, maxAttempts: 5);

    $confirmed = $enrollment->confirmPending(true);

    self::assertTrue($confirmed);
    self::assertTrue($enrollment->isActive());
    self::assertFalse($enrollment->hasPending());
    self::assertSame($secret, $enrollment->activeSecret());
    self::assertInstanceOf(DateTimeImmutable::class, $enrollment->activeConfirmedAt());
    self::assertSame(0, $enrollment->attempts());
  }

  #[Test]
  public function testConfirmPendingFailsOnInvalidCodeAndConsumesAttempt(): void
  {
    $enrollment = TotpEnrollment::startEnrollment(
      userId: 'user-1',
      secret: new TotpSecret('JBSWY3DPEHPK3PXP'),
      maxAttempts: 5,
    );

    $confirmed = $enrollment->confirmPending(false);

    self::assertFalse($confirmed);
    self::assertFalse($enrollment->isActive());
    self::assertTrue($enrollment->hasPending());
    self::assertSame(1, $enrollment->attempts());
    self::assertSame(4, $enrollment->attemptsRemaining());
  }

  #[Test]
  public function testConfirmPendingThrowsWhenNoPendingSecret(): void
  {
    $enrollment = TotpEnrollment::startEnrollment(
      userId: 'user-1',
      secret: new TotpSecret('JBSWY3DPEHPK3PXP'),
      maxAttempts: 5,
    );
    $enrollment->confirmPending(true);

    $this->expectException(TotpEnrollmentNoPendingSecretException::class);

    $enrollment->confirmPending(true);
  }

  #[Test]
  public function testConfirmPendingThrowsWhenAttemptsExhausted(): void
  {
    $enrollment = TotpEnrollment::startEnrollment(
      userId: 'user-1',
      secret: new TotpSecret('JBSWY3DPEHPK3PXP'),
      maxAttempts: 1,
    );
    $enrollment->confirmPending(false);

    $this->expectException(TotpEnrollmentMaxAttemptsException::class);

    $enrollment->confirmPending(false);
  }

  #[Test]
  public function testRequestNewSecretReplacesPendingAndResetsAttempts(): void
  {
    $enrollment = TotpEnrollment::startEnrollment(
      userId: 'user-1',
      secret: new TotpSecret('JBSWY3DPEHPK3PXP'),
      maxAttempts: 3,
    );
    $enrollment->confirmPending(false);
    self::assertSame(1, $enrollment->attempts());

    $newSecret = new TotpSecret('AAAAAAAAAAAAAAAA');
    $enrollment->requestNewSecret($newSecret, 5);

    self::assertTrue($enrollment->hasPending());
    self::assertSame($newSecret, $enrollment->pendingSecret());
    self::assertSame(0, $enrollment->attempts());
    self::assertSame(5, $enrollment->maxAttempts());
  }

  #[Test]
  public function testRequestNewSecretKeepsActiveSecretUntouched(): void
  {
    $activeSecret = new TotpSecret('JBSWY3DPEHPK3PXP');
    $enrollment = TotpEnrollment::startEnrollment(userId: 'user-1', secret: $activeSecret, maxAttempts: 5);
    $enrollment->confirmPending(true);

    $enrollment->requestNewSecret(new TotpSecret('AAAAAAAAAAAAAAAA'), 5);

    self::assertTrue($enrollment->isActive());
    self::assertSame($activeSecret, $enrollment->activeSecret());
    self::assertTrue($enrollment->hasPending());
  }

  #[Test]
  public function testDisableClearsActiveSecretOnValidCode(): void
  {
    $enrollment = TotpEnrollment::startEnrollment(
      userId: 'user-1',
      secret: new TotpSecret('JBSWY3DPEHPK3PXP'),
      maxAttempts: 5,
    );
    $enrollment->confirmPending(true);

    $disabled = $enrollment->disable(true);

    self::assertTrue($disabled);
    self::assertFalse($enrollment->isActive());
    self::assertFalse($enrollment->hasPending());
    self::assertNull($enrollment->activeSecret());
  }

  #[Test]
  public function testDisableFailsOnInvalidCode(): void
  {
    $enrollment = TotpEnrollment::startEnrollment(
      userId: 'user-1',
      secret: new TotpSecret('JBSWY3DPEHPK3PXP'),
      maxAttempts: 5,
    );
    $enrollment->confirmPending(true);

    $disabled = $enrollment->disable(false);

    self::assertFalse($disabled);
    self::assertTrue($enrollment->isActive());
  }

  #[Test]
  public function testDisableThrowsWhenNotActive(): void
  {
    $enrollment = TotpEnrollment::startEnrollment(
      userId: 'user-1',
      secret: new TotpSecret('JBSWY3DPEHPK3PXP'),
      maxAttempts: 5,
    );

    $this->expectException(TotpEnrollmentNotActiveException::class);

    $enrollment->disable(true);
  }

  #[Test]
  public function testReconstituteRestoresFullState(): void
  {
    $now = new DateTimeImmutable();
    $activeSecret = new TotpSecret('JBSWY3DPEHPK3PXP');
    $pendingSecret = new TotpSecret('AAAAAAAAAAAAAAAA');

    $enrollment = TotpEnrollment::reconstitute(
      userId: 'user-1',
      activeSecret: $activeSecret,
      activeConfirmedAt: $now,
      pendingSecret: $pendingSecret,
      pendingCreatedAt: $now,
      attempts: 2,
      maxAttempts: 5,
      createdAt: $now,
      updatedAt: $now,
    );

    self::assertSame('user-1', $enrollment->userId());
    self::assertTrue($enrollment->isActive());
    self::assertTrue($enrollment->hasPending());
    self::assertSame(2, $enrollment->attempts());
    self::assertSame(3, $enrollment->attemptsRemaining());
    self::assertSame($now, $enrollment->createdAt());
    self::assertSame($now, $enrollment->updatedAt());
  }

  #[Test]
  public function testWrongDisableCodesCountUpWithoutLockingBeforeTheLimit(): void
  {
    $enrollment = $this->activeEnrollment();
    $now = new DateTimeImmutable('2026-08-25 10:00:00');

    for ($attempt = 1; $attempt < TotpEnrollment::MAX_DISABLE_ATTEMPTS; ++$attempt) {
      self::assertFalse($enrollment->disable(codeValid: false, now: $now));
      self::assertSame($attempt, $enrollment->disableAttempts());
      self::assertNull($enrollment->disableLockedUntil());
    }
  }

  #[Test]
  public function testTheLastWrongCodeStartsTheCooldownAndClearsTheCounter(): void
  {
    $enrollment = $this->activeEnrollment();
    $now = new DateTimeImmutable('2026-08-25 10:00:00');

    for ($attempt = 0; $attempt < TotpEnrollment::MAX_DISABLE_ATTEMPTS; ++$attempt) {
      $enrollment->disable(codeValid: false, now: $now);
    }

    self::assertSame(0, $enrollment->disableAttempts());
    self::assertEquals(
      $now->add(new DateInterval(TotpEnrollment::DISABLE_LOCK_DURATION)),
      $enrollment->disableLockedUntil(),
    );
  }

  #[Test]
  public function testAFrozenEnrollmentRefusesEvenTheCorrectCode(): void
  {
    // Otherwise the freeze would be no obstacle at all to the one caller it
    // exists to slow down: the one who eventually guesses right.
    $enrollment = $this->lockedEnrollment($now = new DateTimeImmutable('2026-08-25 10:00:00'));

    $this->expectException(TotpDisableTemporarilyLockedException::class);

    $enrollment->disable(codeValid: true, now: $now->modify('+1 minute'));
  }

  #[Test]
  public function testTheFreezeReportsHowLongIsLeft(): void
  {
    $enrollment = $this->lockedEnrollment($now = new DateTimeImmutable('2026-08-25 10:00:00'));

    try {
      $enrollment->disable(codeValid: false, now: $now->modify('+5 minutes'));
      self::fail('Expected the enrollment to be frozen.');
    } catch (TotpDisableTemporarilyLockedException $exception) {
      self::assertSame(600, $exception->retryAfterSeconds);
    }
  }

  #[Test]
  public function testTheEnrollmentThawsOnItsOwnAndTheUserIsNeverStranded(): void
  {
    // The whole reason the lock is temporary: disabling guards the ACTIVE
    // secret, so a permanent lock would leave the user unable to turn TOTP off
    // and unable to re-enroll around it.
    $enrollment = $this->lockedEnrollment($now = new DateTimeImmutable('2026-08-25 10:00:00'));

    $afterCooldown = $now->add(new DateInterval(TotpEnrollment::DISABLE_LOCK_DURATION))->modify('+1 second');

    self::assertTrue($enrollment->disable(codeValid: true, now: $afterCooldown));
    self::assertFalse($enrollment->isActive());
    self::assertNull($enrollment->disableLockedUntil());
    self::assertSame(0, $enrollment->disableAttempts());
  }

  #[Test]
  public function testThawingRestoresAFullAttemptBudget(): void
  {
    $enrollment = $this->lockedEnrollment($now = new DateTimeImmutable('2026-08-25 10:00:00'));

    $afterCooldown = $now->add(new DateInterval(TotpEnrollment::DISABLE_LOCK_DURATION))->modify('+1 second');

    self::assertFalse($enrollment->disable(codeValid: false, now: $afterCooldown));
    self::assertSame(1, $enrollment->disableAttempts());
    self::assertNull($enrollment->disableLockedUntil());
  }

  #[Test]
  public function testASuccessfulDisableClearsTheCounter(): void
  {
    $enrollment = $this->activeEnrollment();
    $now = new DateTimeImmutable('2026-08-25 10:00:00');

    $enrollment->disable(codeValid: false, now: $now);
    self::assertTrue($enrollment->disable(codeValid: true, now: $now));

    self::assertSame(0, $enrollment->disableAttempts());
  }

  #[Test]
  public function testDisableCountingDoesNotConsumeTheConfirmationAttempts(): void
  {
    // The two counters guard different secrets and reset on different events;
    // sharing one would let a failed disable eat the enrollment's budget.
    $enrollment = $this->activeEnrollment();
    $now = new DateTimeImmutable('2026-08-25 10:00:00');

    $enrollment->disable(codeValid: false, now: $now);

    self::assertSame(0, $enrollment->attempts());
  }

  private function activeEnrollment(): TotpEnrollment
  {
    $now = new DateTimeImmutable('2026-08-25 09:00:00');

    return TotpEnrollment::reconstitute(
      userId: 'user-1',
      activeSecret: new TotpSecret('JBSWY3DPEHPK3PXP'),
      activeConfirmedAt: $now,
      pendingSecret: null,
      pendingCreatedAt: null,
      attempts: 0,
      maxAttempts: 5,
      createdAt: $now,
      updatedAt: $now,
    );
  }

  private function lockedEnrollment(DateTimeImmutable $now): TotpEnrollment
  {
    $enrollment = $this->activeEnrollment();

    for ($attempt = 0; $attempt < TotpEnrollment::MAX_DISABLE_ATTEMPTS; ++$attempt) {
      $enrollment->disable(codeValid: false, now: $now);
    }

    return $enrollment;
  }
}
