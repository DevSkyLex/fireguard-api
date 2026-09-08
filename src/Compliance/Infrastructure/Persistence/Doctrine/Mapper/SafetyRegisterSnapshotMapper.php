<?php

declare(strict_types=1);

namespace Compliance\Infrastructure\Persistence\Doctrine\Mapper;

use Compliance\Domain\Model\Snapshot\SafetyRegisterSnapshot;
use Compliance\Domain\ValueObject\SafetyRegisterSnapshotId;
use Compliance\Infrastructure\Persistence\Doctrine\Record\SafetyRegisterSnapshotRecord;

/**
 * Mapper SafetyRegisterSnapshotMapper.
 *
 * @category Mapper
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class SafetyRegisterSnapshotMapper
{
  // #region Methods
  /**
   * Method toDomain.
   *
   * @static
   *
   * Maps a Doctrine snapshot record to the domain model.
   *
   * @since 1.0.0
   *
   * @param SafetyRegisterSnapshotRecord $record the persistence record
   *
   * @return SafetyRegisterSnapshot the domain snapshot
   */
  public static function toDomain(SafetyRegisterSnapshotRecord $record): SafetyRegisterSnapshot
  {
    return SafetyRegisterSnapshot::reconstitute(
      id: SafetyRegisterSnapshotId::fromString($record->id),
      organizationId: $record->organizationId,
      facilityId: $record->facilityId,
      generatedAt: $record->generatedAt,
      generatedByUserId: $record->generatedByUserId,
      contentHash: $record->contentHash,
      sizeBytes: $record->sizeBytes,
      storagePath: $record->storagePath,
      createdAt: $record->createdAt,
    );
  }

  /**
   * Method toRecord.
   *
   * @static
   *
   * Maps a snapshot domain model onto a Doctrine record.
   *
   * @since 1.0.0
   *
   * @param SafetyRegisterSnapshot $snapshot the domain snapshot
   * @param SafetyRegisterSnapshotRecord $record the persistence record to populate
   *
   * @return void no return value
   */
  public static function toRecord(SafetyRegisterSnapshot $snapshot, SafetyRegisterSnapshotRecord $record): void
  {
    $record->id = (string) $snapshot->id();
    $record->organizationId = $snapshot->organizationId();
    $record->facilityId = $snapshot->facilityId();
    $record->generatedAt = $snapshot->generatedAt();
    $record->generatedByUserId = $snapshot->generatedByUserId();
    $record->contentHash = $snapshot->contentHash();
    $record->sizeBytes = $snapshot->sizeBytes();
    $record->storagePath = $snapshot->storagePath();
    $record->createdAt = $snapshot->createdAt();
  }
  // #endregion
}
