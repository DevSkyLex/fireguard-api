<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Persistence\Doctrine\Mapper;

use Inspection\Domain\Model\Inspection\CanonicalInspection;
use Inspection\Domain\ValueObject\{
  InspectionEquipmentId,
  InspectionId,
  InspectionOrganizationId,
  InspectionRecordStatus,
  InspectionResult,
  InspectionStatus
};
use Inspection\Infrastructure\Persistence\Doctrine\Record\InspectionRecord;
use LogicException;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;

/**
 * Mapper CanonicalInspectionMapper.
 *
 * Translates between `InspectionRecord` and the canonical model. Distinct
 * from {@see InspectionMapper}, which maps the same record onto the
 * `Inspection` aggregate and carries a different — deliberately
 * non-overlapping — set of columns.
 *
 * @category Mapper
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CanonicalInspectionMapper
{
  // #region Methods
  /**
   * Method toDomain.
   *
   * @since 1.0.0
   *
   * @param InspectionRecord $record the persisted record
   *
   * @return CanonicalInspection the canonical inspection
   */
  public static function toDomain(InspectionRecord $record): CanonicalInspection
  {
    if (!$record->organization instanceof OrganizationRecord) {
      throw new LogicException('Inspection record must reference an organization.');
    }

    return CanonicalInspection::reconstitute(
      id: InspectionId::fromString($record->id),
      organizationId: InspectionOrganizationId::fromString($record->organization->id),
      equipmentId: InspectionEquipmentId::fromString($record->equipmentId),
      recordStatus: InspectionRecordStatus::from($record->recordStatus),
      interventionId: $record->interventionId,
      status: InspectionStatus::from($record->status),
      result: InspectionResult::from($record->result),
      notes: $record->notes,
      signature: $record->signature,
      revision: $record->revision,
      updatedAt: $record->updatedAt,
    );
  }

  /**
   * Method applyTo.
   *
   * Writes back the six columns the canonical surface may change.
   *
   * `record_status`, `intervention_id`, `organization`, `equipment_id`,
   * `performed_at` and `created_at` are deliberately absent: the canonical
   * PATCH and DELETE never move them, and copying them back would let a
   * stale read overwrite a column another path had changed.
   *
   * @since 1.0.0
   *
   * @param CanonicalInspection $inspection the canonical inspection
   * @param InspectionRecord $record the record to update
   */
  public static function applyTo(CanonicalInspection $inspection, InspectionRecord $record): void
  {
    $record->status = $inspection->status()->value;
    $record->result = $inspection->result()->value;
    $record->notes = $inspection->notes();
    $record->signature = $inspection->signature();
    $record->revision = $inspection->revision();
    $record->updatedAt = $inspection->updatedAt();
  }
  // #endregion
}
