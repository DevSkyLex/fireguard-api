<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Domain\Model\Totp;

use DateTimeImmutable;
use Otp\Domain\Exception\{TotpEnrollmentMaxAttemptsException, TotpEnrollmentNoPendingSecretException, TotpEnrollmentNotActiveException};
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
}
