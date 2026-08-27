<?php

declare(strict_types=1);

namespace Tests\Integration\Import\Infrastructure\Persistence\Doctrine\Mapper;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Import\Domain\Model\ImportJob\ImportJob;
use Import\Domain\ValueObject\{ImportJobId, ImportKind, ImportRowError, ImportStatus};
use Import\Infrastructure\Persistence\Doctrine\Mapper\ImportJobMapper;
use Import\Infrastructure\Persistence\Doctrine\Record\ImportJobRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test ImportJobMapper.
 *
 * `ImportJobMapper` is a stateless, all-static mapper used statically by
 * `ImportJobRepository` (never injected), so it is exercised here through
 * real-database round-trips rather than a container-fetched service: an
 * aggregate is mapped onto a record, persisted with the real `main` entity
 * manager, re-fetched, and mapped back — which additionally validates the
 * JSON `error_report` column round-trip end to end.
 *
 * @category Mapper Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ImportJobMapper::class)]
final class ImportJobMapperTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = '990e8400-e29b-41d4-a716-446655440701';

  private const string COMPLETED_JOB_ID = '990e8400-e29b-41d4-a716-446655440702';

  private const string PENDING_JOB_ID = '990e8400-e29b-41d4-a716-446655440703';

  private const string FAILED_JOB_ID = '990e8400-e29b-41d4-a716-446655440704';

  private const string CREATED_BY = '990e8400-e29b-41d4-a716-446655440709';

  private EntityManagerInterface $entityManager;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    $this->createOrganization();
    $this->entityManager->flush();
    $this->entityManager->clear();
  }

  protected function tearDown(): void
  {
    $this->cleanup();
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testToRecordThenToDomainRoundTripsEveryFieldAndTheErrorReport(): void
  {
    $createdAt = new DateTimeImmutable('2026-01-10T08:00:00+00:00');
    $updatedAt = new DateTimeImmutable('2026-01-10T08:15:00+00:00');
    $startedAt = new DateTimeImmutable('2026-01-10T08:05:00+00:00');
    $completedAt = new DateTimeImmutable('2026-01-10T08:14:00+00:00');

    $job = ImportJob::reconstitute(
      id: ImportJobId::fromString(self::COMPLETED_JOB_ID),
      organizationId: self::ORGANIZATION_ID,
      kind: ImportKind::EQUIPMENT,
      status: ImportStatus::COMPLETED,
      storagePath: 'imports/2026/01/' . self::COMPLETED_JOB_ID . '.csv',
      originalFilename: 'equipment-batch.csv',
      createdBy: self::CREATED_BY,
      createdAt: $createdAt,
      updatedAt: $updatedAt,
      dryRun: false,
      totalRows: 3,
      processedRows: 3,
      successfulRows: 1,
      failedRows: 2,
      errorReport: [
        new ImportRowError(rowNumber: 2, code: 'missing_required', message: 'Name is required.', column: 'name'),
        new ImportRowError(rowNumber: 3, code: 'quota_exceeded', message: 'Plan quota exceeded.'),
      ],
      jobError: null,
      startedAt: $startedAt,
      completedAt: $completedAt,
    );

    // toRecord: assert the record was populated directly, including the JSON
    // error-report shape with a present column and a null column.
    $record = new ImportJobRecord();
    ImportJobMapper::toRecord($job, $record);

    self::assertSame(self::COMPLETED_JOB_ID, $record->id);
    self::assertSame(self::ORGANIZATION_ID, $record->organizationId);
    self::assertSame('equipment', $record->kind);
    self::assertSame('completed', $record->status);
    self::assertSame('equipment-batch.csv', $record->originalFilename);
    self::assertSame(3, $record->totalRows);
    self::assertSame(3, $record->processedRows);
    self::assertSame(1, $record->successfulRows);
    self::assertSame(2, $record->failedRows);
    self::assertNull($record->jobError);
    self::assertSame(
      [
        ['rowNumber' => 2, 'column' => 'name', 'code' => 'missing_required', 'message' => 'Name is required.'],
        ['rowNumber' => 3, 'column' => null, 'code' => 'quota_exceeded', 'message' => 'Plan quota exceeded.'],
      ],
      $record->errorReport,
    );

    $this->entityManager->persist($record);
    $this->entityManager->flush();
    $this->entityManager->clear();

    // toDomain: re-hydrate from the real database and assert every field maps
    // back, exercising both branches of the per-row `column` isset() guard.
    $fetched = $this->entityManager->find(ImportJobRecord::class, self::COMPLETED_JOB_ID);
    self::assertInstanceOf(ImportJobRecord::class, $fetched);

    $mapped = ImportJobMapper::toDomain($fetched);

    self::assertSame(self::COMPLETED_JOB_ID, (string) $mapped->id());
    self::assertSame(self::ORGANIZATION_ID, $mapped->organizationId());
    self::assertSame(ImportKind::EQUIPMENT, $mapped->kind());
    self::assertSame(ImportStatus::COMPLETED, $mapped->status());
    self::assertSame('imports/2026/01/' . self::COMPLETED_JOB_ID . '.csv', $mapped->storagePath());
    self::assertSame('equipment-batch.csv', $mapped->originalFilename());
    self::assertSame(self::CREATED_BY, $mapped->createdBy());
    self::assertSame(3, $mapped->totalRows());
    self::assertSame(3, $mapped->processedRows());
    self::assertSame(1, $mapped->successfulRows());
    self::assertSame(2, $mapped->failedRows());
    self::assertNull($mapped->jobError());
    self::assertSame($createdAt->format('Y-m-d H:i:s'), $mapped->createdAt()->format('Y-m-d H:i:s'));
    self::assertSame($updatedAt->format('Y-m-d H:i:s'), $mapped->updatedAt()->format('Y-m-d H:i:s'));
    self::assertInstanceOf(DateTimeImmutable::class, $mapped->startedAt());
    self::assertSame($startedAt->format('Y-m-d H:i:s'), $mapped->startedAt()->format('Y-m-d H:i:s'));
    self::assertInstanceOf(DateTimeImmutable::class, $mapped->completedAt());
    self::assertSame($completedAt->format('Y-m-d H:i:s'), $mapped->completedAt()->format('Y-m-d H:i:s'));

    $report = $mapped->errorReport();
    self::assertCount(2, $report);

    self::assertSame(2, $report[0]->rowNumber);
    self::assertSame('missing_required', $report[0]->code);
    self::assertSame('Name is required.', $report[0]->message);
    self::assertSame('name', $report[0]->column);

    self::assertSame(3, $report[1]->rowNumber);
    self::assertSame('quota_exceeded', $report[1]->code);
    self::assertSame('Plan quota exceeded.', $report[1]->message);
    self::assertNull($report[1]->column);
  }

  #[Test]
  public function testToDomainMapsANullErrorReportToAnEmptyListAndKeepsNullablesNull(): void
  {
    // A record whose optional columns were never set: null error report, no
    // totals, no start/completion — exercises toDomain's `?? []` fallback and
    // the pass-through of null timestamps.
    $record = new ImportJobRecord();
    $record->id = self::PENDING_JOB_ID;
    $record->organizationId = self::ORGANIZATION_ID;
    $record->kind = 'facility';
    $record->status = 'pending';
    $record->storagePath = 'imports/2026/01/' . self::PENDING_JOB_ID . '.csv';
    $record->originalFilename = 'facilities.csv';
    $record->createdBy = self::CREATED_BY;
    $record->createdAt = new DateTimeImmutable('2026-01-11T09:00:00+00:00');
    $record->updatedAt = $record->createdAt;

    $this->entityManager->persist($record);
    $this->entityManager->flush();
    $this->entityManager->clear();

    $fetched = $this->entityManager->find(ImportJobRecord::class, self::PENDING_JOB_ID);
    self::assertInstanceOf(ImportJobRecord::class, $fetched);

    $mapped = ImportJobMapper::toDomain($fetched);

    self::assertSame(ImportKind::FACILITY, $mapped->kind());
    self::assertSame(ImportStatus::PENDING, $mapped->status());
    self::assertSame([], $mapped->errorReport());
    self::assertNull($mapped->totalRows());
    self::assertSame(0, $mapped->processedRows());
    self::assertSame(0, $mapped->successfulRows());
    self::assertSame(0, $mapped->failedRows());
    self::assertNull($mapped->jobError());
    self::assertNull($mapped->startedAt());
    self::assertNull($mapped->completedAt());
  }

  #[Test]
  public function testToRecordThenToDomainRoundTripsAFailedJobWithAJobErrorAndEmptyReport(): void
  {
    $now = new DateTimeImmutable('2026-01-12T10:00:00+00:00');

    $job = ImportJob::reconstitute(
      id: ImportJobId::fromString(self::FAILED_JOB_ID),
      organizationId: self::ORGANIZATION_ID,
      kind: ImportKind::FACILITY,
      status: ImportStatus::FAILED,
      storagePath: 'imports/2026/01/' . self::FAILED_JOB_ID . '.csv',
      originalFilename: 'broken.csv',
      createdBy: self::CREATED_BY,
      createdAt: $now,
      updatedAt: $now,
      dryRun: false,
      totalRows: null,
      processedRows: 0,
      successfulRows: 0,
      failedRows: 0,
      errorReport: [],
      jobError: 'The uploaded file has an invalid header.',
      startedAt: $now,
      completedAt: $now,
    );

    $record = new ImportJobRecord();
    ImportJobMapper::toRecord($job, $record);

    self::assertSame('failed', $record->status);
    self::assertSame('The uploaded file has an invalid header.', $record->jobError);
    self::assertSame([], $record->errorReport);

    $this->entityManager->persist($record);
    $this->entityManager->flush();
    $this->entityManager->clear();

    $fetched = $this->entityManager->find(ImportJobRecord::class, self::FAILED_JOB_ID);
    self::assertInstanceOf(ImportJobRecord::class, $fetched);

    $mapped = ImportJobMapper::toDomain($fetched);

    self::assertSame(ImportStatus::FAILED, $mapped->status());
    self::assertSame('The uploaded file has an invalid header.', $mapped->jobError());
    self::assertSame([], $mapped->errorReport());
    self::assertInstanceOf(DateTimeImmutable::class, $mapped->completedAt());
    self::assertSame($now->format('Y-m-d H:i:s'), $mapped->completedAt()->format('Y-m-d H:i:s'));
  }

  private function createOrganization(): void
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $organization->name = 'Import Mapper Test';
    $organization->slug = 'import-mapper-test';
    $organization->ownerUserId = self::CREATED_BY;
    $organization->createdByUserId = self::CREATED_BY;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;
    $this->entityManager->persist($organization);
  }

  private function cleanup(): void
  {
    $connection = $this->entityManager->getConnection();
    $connection->executeStatement(
      'DELETE FROM import_jobs WHERE organization_id = :organizationId',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $connection->executeStatement(
      'DELETE FROM organizations WHERE id = :organizationId',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $this->entityManager->clear();
  }
}
