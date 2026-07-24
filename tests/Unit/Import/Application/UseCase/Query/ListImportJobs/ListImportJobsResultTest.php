<?php

declare(strict_types=1);

namespace Tests\Unit\Import\Application\UseCase\Query\ListImportJobs;

use DateTimeImmutable;
use Import\Application\UseCase\Query\GetImportJob\GetImportJobResult;
use Import\Application\UseCase\Query\ListImportJobs\ListImportJobsResult;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ListImportJobsResult.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListImportJobsResult::class)]
final class ListImportJobsResultTest extends TestCase
{
  #[Test]
  public function itExposesAllConstructorValues(): void
  {
    $item = new GetImportJobResult(
      importJobId: 'job-1',
      organizationId: 'org-1',
      kind: 'equipment',
      status: 'pending',
      originalFilename: 'equipment.csv',
      totalRows: null,
      processedRows: 0,
      successfulRows: 0,
      failedRows: 0,
      errorReport: [],
      jobError: null,
      createdBy: 'user-1',
      createdAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      startedAt: null,
      completedAt: null,
      updatedAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    );

    $result = new ListImportJobsResult(items: [$item], page: 1, itemsPerPage: 30, total: 1);

    self::assertSame([$item], $result->items);
    self::assertSame(1, $result->page);
    self::assertSame(30, $result->itemsPerPage);
    self::assertSame(1, $result->total);
  }

  #[Test]
  public function itSupportsAnEmptyPage(): void
  {
    $result = new ListImportJobsResult(items: [], page: 3, itemsPerPage: 10, total: 0);

    self::assertSame([], $result->items);
    self::assertSame(3, $result->page);
    self::assertSame(0, $result->total);
  }
}
