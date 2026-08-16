<?php

declare(strict_types=1);

namespace Tests\Unit\Import\Presentation\Api\Factory;

use DateTimeImmutable;
use Import\Application\UseCase\Command\CreateImportJob\CreateImportJobResult;
use Import\Application\UseCase\Query\GetImportJob\GetImportJobResult;
use Import\Presentation\Api\Factory\ImportJobOutputFactory;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ImportJobOutputFactoryTest.
 *
 * @category Factory Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ImportJobOutputFactory::class)]
final class ImportJobOutputFactoryTest extends TestCase
{
  // #region Constants
  private const string IMPORT_JOB_ID = '550e8400-e29b-41d4-a716-446655442000';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440010';
  // #endregion

  // #region Methods
  #[Test]
  public function testFromResultMirrorsTheCreationTimestampOnBothDateFields(): void
  {
    $output = new ImportJobOutputFactory()->fromResult(new CreateImportJobResult(
      importJobId: self::IMPORT_JOB_ID,
      organizationId: self::ORGANIZATION_ID,
      kind: 'equipment',
      status: 'pending',
      originalFilename: 'equipment.csv',
      createdAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    ));

    self::assertSame(self::IMPORT_JOB_ID, $output->id);
    self::assertSame('/api/organizations/' . self::ORGANIZATION_ID, $output->organization);
    self::assertSame('equipment', $output->kind);
    self::assertSame('pending', $output->status);
    self::assertSame('equipment.csv', $output->originalFilename);
    self::assertSame('2026-01-01T00:00:00+00:00', $output->createdAt);
    self::assertSame('2026-01-01T00:00:00+00:00', $output->updatedAt);
    self::assertFalse($output->dryRun);
  }

  #[Test]
  public function testFromViewMapsTheCountersAndTheErrorReport(): void
  {
    $output = new ImportJobOutputFactory()->fromView($this->view());

    self::assertSame(self::IMPORT_JOB_ID, $output->id);
    self::assertSame(10, $output->totalRows);
    self::assertSame(10, $output->processedRows);
    self::assertSame(8, $output->successfulRows);
    self::assertSame(2, $output->failedRows);
    self::assertSame('partial failure', $output->jobError);
    self::assertSame('2026-01-01T01:00:00+00:00', $output->startedAt);
    self::assertSame('2026-01-01T02:00:00+00:00', $output->completedAt);

    self::assertCount(2, $output->errorReport);
    self::assertSame(3, $output->errorReport[0]->rowNumber);
    self::assertSame('serial', $output->errorReport[0]->column);
    self::assertSame('duplicate', $output->errorReport[0]->code);
    self::assertSame('Serial already used.', $output->errorReport[0]->message);
    self::assertNull($output->errorReport[1]->column);
    self::assertFalse($output->dryRun);
  }

  #[Test]
  public function testFromViewLeavesTheOptionalTimestampsNullWhileTheJobIsQueued(): void
  {
    $output = new ImportJobOutputFactory()->fromView($this->view(
      startedAt: null,
      completedAt: null,
    ));

    self::assertNull($output->startedAt);
    self::assertNull($output->completedAt);
  }

  #[Test]
  public function testFromViewCarriesTheDryRunFlagAndAWouldCreateReportEntry(): void
  {
    $view = new GetImportJobResult(
      importJobId: self::IMPORT_JOB_ID,
      organizationId: self::ORGANIZATION_ID,
      kind: 'facility',
      status: 'completed',
      originalFilename: 'facilities.csv',
      dryRun: true,
      totalRows: 1,
      processedRows: 1,
      successfulRows: 1,
      failedRows: 0,
      errorReport: [
        ['rowNumber' => 1, 'column' => null, 'code' => 'would_create', 'message' => 'Would create this facility.'],
      ],
      jobError: null,
      createdBy: '550e8400-e29b-41d4-a716-446655440001',
      createdAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      startedAt: new DateTimeImmutable('2026-01-01T00:00:01+00:00'),
      completedAt: new DateTimeImmutable('2026-01-01T00:00:02+00:00'),
      updatedAt: new DateTimeImmutable('2026-01-01T00:00:02+00:00'),
    );

    $output = new ImportJobOutputFactory()->fromView($view);

    self::assertTrue($output->dryRun);
    self::assertCount(1, $output->errorReport);
    self::assertSame('would_create', $output->errorReport[0]->code);
  }

  private function view(
    ?DateTimeImmutable $startedAt = new DateTimeImmutable('2026-01-01T01:00:00+00:00'),
    ?DateTimeImmutable $completedAt = new DateTimeImmutable('2026-01-01T02:00:00+00:00'),
  ): GetImportJobResult {
    return new GetImportJobResult(
      importJobId: self::IMPORT_JOB_ID,
      organizationId: self::ORGANIZATION_ID,
      kind: 'equipment',
      status: 'completed',
      originalFilename: 'equipment.csv',
      dryRun: false,
      totalRows: 10,
      processedRows: 10,
      successfulRows: 8,
      failedRows: 2,
      errorReport: [
        ['rowNumber' => 3, 'column' => 'serial', 'code' => 'duplicate', 'message' => 'Serial already used.'],
        ['rowNumber' => 7, 'column' => null, 'code' => 'invalid_row', 'message' => 'Row could not be parsed.'],
      ],
      jobError: 'partial failure',
      createdBy: '550e8400-e29b-41d4-a716-446655440001',
      createdAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      startedAt: $startedAt,
      completedAt: $completedAt,
      updatedAt: new DateTimeImmutable('2026-01-01T02:00:00+00:00'),
    );
  }
  // #endregion
}
