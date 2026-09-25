<?php

declare(strict_types=1);

namespace Import\Infrastructure\Persistence\Doctrine\Mapper;

use Import\Domain\Model\ImportJob\{ImportJob, ImportJobProgress, ImportJobSource, ImportJobTimeline};
use Import\Domain\ValueObject\{ImportJobId, ImportKind, ImportRowError, ImportStatus};
use Import\Infrastructure\Persistence\Doctrine\Record\ImportJobRecord;

use function array_map;

/**
 * Mapper ImportJobMapper.
 *
 * @category Mapper
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ImportJobMapper
{
  // #region Methods
  /**
   * Method toDomain.
   *
   * @static
   *
   * Maps a Doctrine import job record to a domain aggregate.
   *
   * @since 1.0.0
   *
   * @param ImportJobRecord $record the persistence record
   *
   * @return ImportJob the domain aggregate
   */
  public static function toDomain(ImportJobRecord $record): ImportJob
  {
    return ImportJob::reconstitute(
      source: new ImportJobSource(
        ImportJobId::fromString($record->id),
        $record->organizationId,
        ImportKind::from($record->kind),
        $record->storagePath,
        $record->originalFilename,
        $record->createdBy,
        $record->dryRun,
      ),
      progress: new ImportJobProgress(
        ImportStatus::from($record->status),
        $record->totalRows,
        $record->processedRows,
        $record->successfulRows,
        $record->failedRows,
        array_map(
          static fn (array $error): ImportRowError => new ImportRowError(
            rowNumber: (int) $error['rowNumber'],
            code: (string) $error['code'],
            message: (string) $error['message'],
            column: isset($error['column']) ? (string) $error['column'] : null,
          ),
          $record->errorReport ?? [],
        ),
        $record->jobError,
      ),
      timeline: new ImportJobTimeline($record->createdAt, $record->updatedAt, $record->startedAt, $record->completedAt),
      confirmedJobId: $record->confirmedJobId,
    );
  }

  /**
   * Method toRecord.
   *
   * @static
   *
   * Maps an import job aggregate onto a Doctrine record.
   *
   * @since 1.0.0
   *
   * @param ImportJob $job the domain aggregate
   * @param ImportJobRecord $record the persistence record to populate
   */
  public static function toRecord(ImportJob $job, ImportJobRecord $record): void
  {
    $record->id = (string) $job->id();
    $record->organizationId = $job->organizationId();
    $record->kind = $job->kind()->value;
    $record->status = $job->status()->value;
    $record->storagePath = $job->storagePath();
    $record->originalFilename = $job->originalFilename();
    $record->dryRun = $job->isDryRun();
    $record->totalRows = $job->totalRows();
    $record->processedRows = $job->processedRows();
    $record->successfulRows = $job->successfulRows();
    $record->failedRows = $job->failedRows();
    $record->errorReport = array_map(
      static fn (ImportRowError $error): array => [
        'rowNumber' => $error->rowNumber,
        'column' => $error->column,
        'code' => $error->code,
        'message' => $error->message,
      ],
      $job->errorReport(),
    );
    $record->jobError = $job->jobError();
    $record->createdBy = $job->createdBy();
    $record->createdAt = $job->createdAt();
    $record->updatedAt = $job->updatedAt();
    $record->startedAt = $job->startedAt();
    $record->completedAt = $job->completedAt();
    $record->confirmedJobId = $job->confirmedJobId();
  }
  // #endregion
}
