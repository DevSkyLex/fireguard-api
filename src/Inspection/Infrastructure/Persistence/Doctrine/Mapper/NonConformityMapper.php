<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Persistence\Doctrine\Mapper;

use Inspection\Domain\Model\NonConformity\{NonConformity, RestoredNonConformityResolution};
use Inspection\Domain\ValueObject\{
  NonConformityId,
  NonConformityInspectionId,
  NonConformitySeverity,
  NonConformityStatus
};
use Inspection\Infrastructure\Persistence\Doctrine\Record\{InspectionRecord, NonConformityRecord};
use LogicException;

/**
 * Class NonConformityMapper
 *
 * Converts non-conformity domain state to and from its Doctrine record representation.
 *
 * @category Mapper
 */
final class NonConformityMapper
{
  // #region Methods
  /**
   * Method toDomain
   *
   * Reconstitutes a non-conformity from its record and requires its inspection relation.
   *
   * @access public
   *
   * @param NonConformityRecord $record persisted non-conformity state
   *
   * @return NonConformity reconstructed domain object
   *
   * @throws LogicException when the record has no inspection record
   */
  public static function toDomain(NonConformityRecord $record): NonConformity
  {
    if (!$record->inspection instanceof InspectionRecord) {
      throw new LogicException('Non-conformity record must reference an inspection.');
    }

    return NonConformity::reconstitute(
      id: NonConformityId::fromString($record->id),
      inspectionId: NonConformityInspectionId::fromString($record->inspection->id),
      description: $record->description,
      severity: NonConformitySeverity::from($record->severity),
      resolution: new RestoredNonConformityResolution(
        status: NonConformityStatus::from($record->status),
        dueAt: $record->dueAt,
        resolvedAt: $record->resolvedAt,
        notes: $record->notes,
      ),
      createdAt: $record->createdAt,
      updatedAt: $record->updatedAt,
    );
  }

  /**
   * Method toRecord
   *
   * Copies domain state into a new Doctrine record for persistence.
   *
   * @access public
   *
   * @param NonConformity $nonConformity domain state to persist
   *
   * @return NonConformityRecord record populated from the domain object
   */
  public static function toRecord(NonConformity $nonConformity): NonConformityRecord
  {
    $record = new NonConformityRecord();
    $record->id = (string) $nonConformity->id();
    $record->description = $nonConformity->description();
    $record->severity = $nonConformity->severity()->value;
    $record->status = $nonConformity->status()->value;
    $record->dueAt = $nonConformity->dueAt();
    $record->resolvedAt = $nonConformity->resolvedAt();
    $record->notes = $nonConformity->notes();
    $record->createdAt = $nonConformity->createdAt();
    $record->updatedAt = $nonConformity->updatedAt();

    return $record;
  }
  // #endregion
}
