<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\Persistence\Doctrine\Mapper;

use Equipment\Domain\Model\Equipment\CanonicalEquipment;
use Equipment\Domain\ValueObject\{EquipmentId, EquipmentOrganizationId, EquipmentRecordStatus, EquipmentStatus};
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use LogicException;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;

/**
 * Mapper CanonicalEquipmentMapper.
 *
 * Translates between `EquipmentRecord` and the canonical model. Distinct
 * from {@see EquipmentMapper}, which maps the same record onto the
 * `Equipment` aggregate and carries a different — deliberately
 * non-overlapping — set of columns.
 *
 * @category Mapper
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CanonicalEquipmentMapper
{
  // #region Methods
  /**
   * Method toDomain.
   *
   * @since 1.0.0
   *
   * @param EquipmentRecord $record the persisted record
   *
   * @return CanonicalEquipment the canonical equipment
   */
  public static function toDomain(EquipmentRecord $record): CanonicalEquipment
  {
    if (!$record->organization instanceof OrganizationRecord) {
      throw new LogicException('Equipment record must reference an organization.');
    }

    return CanonicalEquipment::reconstitute(
      id: EquipmentId::fromString($record->id),
      organizationId: EquipmentOrganizationId::fromString($record->organization->id),
      recordStatus: EquipmentRecordStatus::from($record->recordStatus),
      interventionId: $record->interventionId,
      facilityId: $record->facilityId,
      type: $record->type,
      subType: $record->subType,
      brand: $record->brand,
      model: $record->model,
      serialNumber: $record->serialNumber,
      locationLabel: $record->locationLabel,
      status: EquipmentStatus::from($record->status),
      commissionedAt: $record->commissionedAt,
      revision: $record->revision,
      updatedAt: $record->updatedAt,
    );
  }

  /**
   * Method applyTo.
   *
   * Writes back the ten columns the canonical surface may change.
   *
   * `record_status`, `intervention_id`, `client_id`, `organization`,
   * `installed_at` and `created_at` are deliberately absent: the canonical
   * PATCH and DELETE never move them, and copying them back would let a
   * stale read overwrite a column another path had changed.
   *
   * @since 1.0.0
   *
   * @param CanonicalEquipment $equipment the canonical equipment
   * @param EquipmentRecord $record the record to update
   */
  public static function applyTo(CanonicalEquipment $equipment, EquipmentRecord $record): void
  {
    $record->facilityId = $equipment->facilityId();
    $record->type = $equipment->type();
    $record->subType = $equipment->subType();
    $record->brand = $equipment->brand();
    $record->model = $equipment->model();
    $record->serialNumber = $equipment->serialNumber();
    $record->locationLabel = $equipment->locationLabel();
    $record->status = $equipment->status()->value;
    $record->commissionedAt = $equipment->commissionedAt();
    $record->revision = $equipment->revision();
    $record->updatedAt = $equipment->updatedAt();
  }
  // #endregion
}
