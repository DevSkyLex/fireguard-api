<?php

declare(strict_types=1);

namespace Tests\Unit\Import\Domain\Model\ImportJob;

use DateTimeImmutable;
use Import\Domain\Model\ImportJob\ImportJob;
use Import\Domain\ValueObject\{ImportJobId, ImportKind, ImportRowError, ImportStatus};
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ImportJob.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ImportJob::class)]
final class ImportJobTest extends TestCase
{
  private const string JOB_ID = '018f0b68-6758-7a12-8a1d-3f0d97f65a01';

  private const string ORGANIZATION_ID = 'org-1';

  private const string CREATED_BY = 'user-1';

  #[Test]
  public function itCreatesAPendingJobWithTheGivenAttributes(): void
  {
    $job = $this->pendingEquipmentJob();

    self::assertSame(self::JOB_ID, (string) $job->id());
    self::assertSame(self::ORGANIZATION_ID, $job->organizationId());
    self::assertSame(ImportKind::EQUIPMENT, $job->kind());
    self::assertSame(ImportStatus::PENDING, $job->status());
    self::assertSame('imports/org-1/job.csv', $job->storagePath());
    self::assertSame('equipment.csv', $job->originalFilename());
    self::assertSame(self::CREATED_BY, $job->createdBy());
    self::assertNull($job->totalRows());
    self::assertSame(0, $job->processedRows());
    self::assertSame(0, $job->successfulRows());
    self::assertSame(0, $job->failedRows());
    self::assertSame([], $job->errorReport());
    self::assertNull($job->jobError());
    self::assertNull($job->startedAt());
    self::assertNull($job->completedAt());
  }

  #[Test]
  public function itReconstitutesFromPersistedState(): void
  {
    $createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $updatedAt = new DateTimeImmutable('2026-01-02T00:00:00+00:00');
    $error = new ImportRowError(1, 'invalid', 'bad', 'type');

    $job = ImportJob::reconstitute(
      id: ImportJobId::fromString(self::JOB_ID),
      organizationId: self::ORGANIZATION_ID,
      kind: ImportKind::FACILITY,
      status: ImportStatus::COMPLETED,
      storagePath: 'imports/org-1/job.csv',
      originalFilename: 'facilities.csv',
      createdBy: self::CREATED_BY,
      createdAt: $createdAt,
      updatedAt: $updatedAt,
      totalRows: 5,
      processedRows: 5,
      successfulRows: 4,
      failedRows: 1,
      errorReport: [$error],
      jobError: null,
      startedAt: $createdAt,
      completedAt: $updatedAt,
    );

    self::assertSame(ImportKind::FACILITY, $job->kind());
    self::assertSame(ImportStatus::COMPLETED, $job->status());
    self::assertSame(5, $job->totalRows());
    self::assertSame(5, $job->processedRows());
    self::assertSame(4, $job->successfulRows());
    self::assertSame(1, $job->failedRows());
    self::assertSame([$error], $job->errorReport());
    self::assertSame($createdAt, $job->createdAt());
    self::assertSame($updatedAt, $job->updatedAt());
    self::assertSame($createdAt, $job->startedAt());
    self::assertSame($updatedAt, $job->completedAt());
  }

  #[Test]
  public function itTransitionsFromPendingToProcessing(): void
  {
    $job = $this->pendingEquipmentJob();
    $now = new DateTimeImmutable('2026-01-05T10:00:00+00:00');

    $job->markProcessing($now);

    self::assertSame(ImportStatus::PROCESSING, $job->status());
    self::assertSame($now, $job->startedAt());
  }

  #[Test]
  public function itRejectsProcessingWhenNotPending(): void
  {
    $job = $this->pendingEquipmentJob();
    $job->markProcessing(new DateTimeImmutable());

    $this->expectException(InvalidArgumentException::class);

    $job->markProcessing(new DateTimeImmutable());
  }

  #[Test]
  public function itRecordsTotalsAndRowOutcomesWhileProcessing(): void
  {
    $job = $this->pendingEquipmentJob();
    $job->markProcessing(new DateTimeImmutable());

    $job->setTotalRows(3);
    $job->recordRowSuccess();
    $job->recordRowError(new ImportRowError(2, 'quota_exceeded', 'Plan limit reached.'));

    self::assertSame(3, $job->totalRows());
    self::assertSame(2, $job->processedRows());
    self::assertSame(1, $job->successfulRows());
    self::assertSame(1, $job->failedRows());
    self::assertCount(1, $job->errorReport());
    self::assertSame('quota_exceeded', $job->errorReport()[0]->code);
  }

  #[Test]
  public function itRejectsRecordingRowsWhenNotProcessing(): void
  {
    $job = $this->pendingEquipmentJob();

    $this->expectException(InvalidArgumentException::class);

    $job->recordRowSuccess();
  }

  #[Test]
  public function itCompletesFromProcessing(): void
  {
    $job = $this->pendingEquipmentJob();
    $job->markProcessing(new DateTimeImmutable());
    $completedAt = new DateTimeImmutable('2026-01-05T12:00:00+00:00');

    $job->complete($completedAt);

    self::assertSame(ImportStatus::COMPLETED, $job->status());
    self::assertSame($completedAt, $job->completedAt());
  }

  #[Test]
  public function itFailsFromANonTerminalStatus(): void
  {
    $job = $this->pendingEquipmentJob();
    $now = new DateTimeImmutable('2026-01-05T12:00:00+00:00');

    $job->fail('CSV header is unreadable.', $now);

    self::assertSame(ImportStatus::FAILED, $job->status());
    self::assertSame('CSV header is unreadable.', $job->jobError());
    self::assertSame($now, $job->completedAt());
  }

  #[Test]
  public function itRejectsFailingATerminalJob(): void
  {
    $job = $this->pendingEquipmentJob();
    $job->markProcessing(new DateTimeImmutable());
    $job->complete(new DateTimeImmutable());

    $this->expectException(InvalidArgumentException::class);

    $job->fail('too late', new DateTimeImmutable());
  }

  private function pendingEquipmentJob(): ImportJob
  {
    return ImportJob::create(
      id: ImportJobId::fromString(self::JOB_ID),
      organizationId: self::ORGANIZATION_ID,
      kind: ImportKind::EQUIPMENT,
      storagePath: 'imports/org-1/job.csv',
      originalFilename: 'equipment.csv',
      createdBy: self::CREATED_BY,
    );
  }
}
