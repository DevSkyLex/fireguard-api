<?php

declare(strict_types=1);

namespace Tests\Unit\Import\Application\UseCase\Query\GetImportJob;

use DateTimeImmutable;
use Import\Application\UseCase\Query\GetImportJob\GetImportJobResult;
use Import\Domain\Model\ImportJob\ImportJob;
use Import\Domain\ValueObject\{ImportJobId, ImportKind, ImportRowError, ImportStatus};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test GetImportJobResult.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetImportJobResult::class)]
final class GetImportJobResultTest extends TestCase
{
  private const string JOB_ID = '018f0b68-6758-7a12-8a1d-3f0d97f65a01';

  #[Test]
  public function itExposesAllConstructorValues(): void
  {
    $createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $updatedAt = new DateTimeImmutable('2026-01-02T00:00:00+00:00');

    $result = new GetImportJobResult(
      importJobId: 'job-1',
      organizationId: 'org-1',
      kind: 'equipment',
      status: 'completed',
      originalFilename: 'equipment.csv',
      dryRun: false,
      totalRows: 2,
      processedRows: 2,
      successfulRows: 1,
      failedRows: 1,
      errorReport: [['rowNumber' => 2, 'column' => 'type', 'code' => 'invalid', 'message' => 'bad']],
      jobError: null,
      createdBy: 'user-1',
      createdAt: $createdAt,
      startedAt: $createdAt,
      completedAt: $updatedAt,
      updatedAt: $updatedAt,
    );

    self::assertSame('job-1', $result->importJobId);
    self::assertSame('org-1', $result->organizationId);
    self::assertSame('equipment', $result->kind);
    self::assertSame('completed', $result->status);
    self::assertSame(2, $result->totalRows);
    self::assertSame(1, $result->successfulRows);
    self::assertSame(1, $result->failedRows);
    self::assertCount(1, $result->errorReport);
    self::assertNull($result->jobError);
    self::assertSame('user-1', $result->createdBy);
    self::assertSame($updatedAt, $result->updatedAt);
    self::assertFalse($result->dryRun);
  }

  #[Test]
  public function itBuildsTheReadViewFromTheDomainAggregate(): void
  {
    $job = ImportJob::create(
      id: ImportJobId::fromString(self::JOB_ID),
      organizationId: 'org-1',
      kind: ImportKind::EQUIPMENT,
      storagePath: 'imports/org-1/job.csv',
      originalFilename: 'equipment.csv',
      createdBy: 'user-1',
    );
    $job->markProcessing(new DateTimeImmutable());
    $job->setTotalRows(2);
    $job->recordRowSuccess();
    $job->recordRowError(new ImportRowError(2, 'invalid', 'bad value', 'type'));
    $job->complete(new DateTimeImmutable());

    $result = GetImportJobResult::fromDomain($job);

    self::assertSame(self::JOB_ID, $result->importJobId);
    self::assertSame('org-1', $result->organizationId);
    self::assertSame('equipment', $result->kind);
    self::assertSame(ImportStatus::COMPLETED->value, $result->status);
    self::assertSame('equipment.csv', $result->originalFilename);
    self::assertSame(2, $result->totalRows);
    self::assertSame(2, $result->processedRows);
    self::assertSame(1, $result->successfulRows);
    self::assertSame(1, $result->failedRows);
    self::assertSame('user-1', $result->createdBy);
    self::assertSame(
      [['rowNumber' => 2, 'column' => 'type', 'code' => 'invalid', 'message' => 'bad value']],
      $result->errorReport,
    );
    self::assertFalse($result->dryRun);
  }
}
