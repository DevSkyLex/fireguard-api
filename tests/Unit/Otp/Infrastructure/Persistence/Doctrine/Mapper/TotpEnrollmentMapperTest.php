<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Infrastructure\Persistence\Doctrine\Mapper;

use DateTimeImmutable;
use Otp\Domain\Model\Totp\TotpEnrollment;
use Otp\Domain\ValueObject\TotpSecret;
use Otp\Infrastructure\Persistence\Doctrine\Mapper\TotpEnrollmentMapper;
use Otp\Infrastructure\Persistence\Doctrine\Record\TotpEnrollmentRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test TotpEnrollmentMapperTest.
 *
 * @category Mapper Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(TotpEnrollmentMapper::class)]
final class TotpEnrollmentMapperTest extends TestCase
{
  // #region Constants
  private const string USER_ID = '0199a7c1-0000-7000-8000-0000000000f1';

  private const string ACTIVE_SECRET = 'JBSWY3DPEHPK3PXP';

  private const string PENDING_SECRET = 'KRSXG5CTMVRXEZLU';
  // #endregion

  // #region Methods
  #[Test]
  public function testToRecordCopiesEveryField(): void
  {
    $activeConfirmedAt = new DateTimeImmutable('2026-01-10T10:00:00+00:00');
    $pendingCreatedAt = new DateTimeImmutable('2026-01-11T10:00:00+00:00');
    $createdAt = new DateTimeImmutable('2026-01-01T10:00:00+00:00');
    $updatedAt = new DateTimeImmutable('2026-01-12T10:00:00+00:00');

    $enrollment = TotpEnrollment::reconstitute(
      userId: self::USER_ID,
      activeSecret: new TotpSecret(self::ACTIVE_SECRET),
      activeConfirmedAt: $activeConfirmedAt,
      pendingSecret: new TotpSecret(self::PENDING_SECRET),
      pendingCreatedAt: $pendingCreatedAt,
      attempts: 2,
      maxAttempts: 5,
      createdAt: $createdAt,
      updatedAt: $updatedAt,
    );

    $record = new TotpEnrollmentMapper()->toRecord($enrollment);

    self::assertSame(self::USER_ID, $record->getUserId());
    self::assertSame(self::ACTIVE_SECRET, $record->getActiveSecret());
    self::assertSame($activeConfirmedAt, $record->getActiveConfirmedAt());
    self::assertSame(self::PENDING_SECRET, $record->getPendingSecret());
    self::assertSame($pendingCreatedAt, $record->getPendingCreatedAt());
    self::assertSame(2, $record->getAttempts());
    self::assertSame(5, $record->getMaxAttempts());
    self::assertSame($createdAt, $record->getCreatedAt());
    self::assertSame($updatedAt, $record->getUpdatedAt());
  }

  #[Test]
  public function testToRecordPopulatesAnExistingRecordInPlace(): void
  {
    $existing = new TotpEnrollmentRecord();

    $returned = new TotpEnrollmentMapper()->toRecord($this->enrollment(), $existing);

    self::assertSame($existing, $returned);
    self::assertSame(self::USER_ID, $existing->getUserId());
  }

  #[Test]
  public function testToRecordLeavesUnsetSecretsNull(): void
  {
    $timestamp = new DateTimeImmutable('2026-01-01T10:00:00+00:00');

    $enrollment = TotpEnrollment::reconstitute(
      userId: self::USER_ID,
      activeSecret: null,
      activeConfirmedAt: null,
      pendingSecret: null,
      pendingCreatedAt: null,
      attempts: 0,
      maxAttempts: 5,
      createdAt: $timestamp,
      updatedAt: $timestamp,
    );

    $record = new TotpEnrollmentMapper()->toRecord($enrollment);

    self::assertNull($record->getActiveSecret());
    self::assertNull($record->getActiveConfirmedAt());
    self::assertNull($record->getPendingSecret());
    self::assertNull($record->getPendingCreatedAt());
    self::assertSame(0, $record->getAttempts());
  }

  #[Test]
  public function testToDomainRebuildsTheAggregate(): void
  {
    $activeConfirmedAt = new DateTimeImmutable('2026-02-10T10:00:00+00:00');
    $pendingCreatedAt = new DateTimeImmutable('2026-02-11T10:00:00+00:00');
    $createdAt = new DateTimeImmutable('2026-02-01T10:00:00+00:00');
    $updatedAt = new DateTimeImmutable('2026-02-12T10:00:00+00:00');

    $record = new TotpEnrollmentRecord()
      ->setUserId(self::USER_ID)
      ->setActiveSecret(self::ACTIVE_SECRET)
      ->setActiveConfirmedAt($activeConfirmedAt)
      ->setPendingSecret(self::PENDING_SECRET)
      ->setPendingCreatedAt($pendingCreatedAt)
      ->setAttempts(1)
      ->setMaxAttempts(3)
      ->setCreatedAt($createdAt)
      ->setUpdatedAt($updatedAt);

    $enrollment = new TotpEnrollmentMapper()->toDomain($record);

    self::assertSame(self::USER_ID, $enrollment->userId());
    self::assertInstanceOf(TotpSecret::class, $enrollment->activeSecret());
    self::assertSame(self::ACTIVE_SECRET, $enrollment->activeSecret()->secret);
    self::assertSame($activeConfirmedAt, $enrollment->activeConfirmedAt());
    self::assertInstanceOf(TotpSecret::class, $enrollment->pendingSecret());
    self::assertSame(self::PENDING_SECRET, $enrollment->pendingSecret()->secret);
    self::assertSame($pendingCreatedAt, $enrollment->pendingCreatedAt());
    self::assertSame(1, $enrollment->attempts());
    self::assertSame(3, $enrollment->maxAttempts());
    self::assertSame($createdAt, $enrollment->createdAt());
    self::assertSame($updatedAt, $enrollment->updatedAt());
  }

  #[Test]
  public function testToDomainLeavesAbsentSecretsNull(): void
  {
    $timestamp = new DateTimeImmutable('2026-02-01T10:00:00+00:00');

    $record = new TotpEnrollmentRecord()
      ->setUserId(self::USER_ID)
      ->setActiveSecret(null)
      ->setActiveConfirmedAt(null)
      ->setPendingSecret(null)
      ->setPendingCreatedAt(null)
      ->setAttempts(0)
      ->setMaxAttempts(5)
      ->setCreatedAt($timestamp)
      ->setUpdatedAt($timestamp);

    $enrollment = new TotpEnrollmentMapper()->toDomain($record);

    self::assertNull($enrollment->activeSecret());
    self::assertNull($enrollment->pendingSecret());
    self::assertNull($enrollment->activeConfirmedAt());
    self::assertNull($enrollment->pendingCreatedAt());
  }

  #[Test]
  public function testRoundTripPreservesState(): void
  {
    $mapper = new TotpEnrollmentMapper();

    $roundTripped = $mapper->toRecord($mapper->toDomain($mapper->toRecord($this->enrollment())));

    self::assertSame(self::USER_ID, $roundTripped->getUserId());
    self::assertSame(self::ACTIVE_SECRET, $roundTripped->getActiveSecret());
    self::assertSame(4, $roundTripped->getMaxAttempts());
  }
  // #endregion

  // #region Helpers
  private function enrollment(): TotpEnrollment
  {
    $timestamp = new DateTimeImmutable('2026-03-01T10:00:00+00:00');

    return TotpEnrollment::reconstitute(
      userId: self::USER_ID,
      activeSecret: new TotpSecret(self::ACTIVE_SECRET),
      activeConfirmedAt: $timestamp,
      pendingSecret: null,
      pendingCreatedAt: null,
      attempts: 0,
      maxAttempts: 4,
      createdAt: $timestamp,
      updatedAt: $timestamp,
    );
  }
  // #endregion
}
