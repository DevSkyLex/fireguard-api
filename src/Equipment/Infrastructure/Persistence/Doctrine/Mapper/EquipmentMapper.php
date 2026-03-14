<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\Persistence\Doctrine\Mapper;

use Equipment\Domain\Model\Equipment\Equipment;
use Equipment\Domain\ValueObject\{
  EquipmentFacilityId,
  EquipmentId,
  EquipmentOrganizationId,
  EquipmentStatus,
  EquipmentType
};
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use LogicException;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;

/**
 * Mapper EquipmentMapper.
 *
 * @category Mapper
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class EquipmentMapper
{
  // #region Methods
  /**
   * Method toDomain.
   *
   * Maps a Doctrine equipment record to a domain aggregate.
   *
   * @since 1.0.0
   *
   * @param EquipmentRecord $record the persistence record
   *
   * @return Equipment the domain aggregate
   */
  public static function toDomain(EquipmentRecord $record): Equipment
  {
    if (!$record->organization instanceof OrganizationRecord) {
      throw new LogicException('Equipment record must reference an organization.');
    }

    return Equipment::reconstitute(
      id: EquipmentId::fromString($record->id),
      organizationId: EquipmentOrganizationId::fromString($record->organization->id),
      type: EquipmentType::from($record->type),
      status: EquipmentStatus::from($record->status),
      createdAt: $record->createdAt,
      updatedAt: $record->updatedAt,
      facilityId: null !== $record->facilityId ? EquipmentFacilityId::fromString($record->facilityId) : null,
      subType: $record->subType,
      brand: $record->brand,
      model: $record->model,
      serialNumber: $record->serialNumber,
      locationLabel: $record->locationLabel,
      installedAt: $record->installedAt,
      commissionedAt: $record->commissionedAt,
    );
  }

  /**
   * Method toRecord.
   *
   * Maps an equipment aggregate to a Doctrine record.
   *
   * @since 1.0.0
   *
   * @param Equipment $equipment the domain aggregate
   *
   * @return EquipmentRecord the persistence record
   */
  public static function toRecord(Equipment $equipment): EquipmentRecord
  {
    $record = new EquipmentRecord();
    $record->id = (string) $equipment->id();
    $record->facilityId = $equipment->facilityId()?->__toString();
    $record->type = $equipment->type()->value;
    $record->subType = $equipment->subType();
    $record->brand = $equipment->brand();
    $record->model = $equipment->model();
    $record->serialNumber = $equipment->serialNumber();
    $record->locationLabel = $equipment->locationLabel();
    $record->status = $equipment->status()->value;
    $record->installedAt = $equipment->installedAt();
    $record->commissionedAt = $equipment->commissionedAt();
    $record->createdAt = $equipment->createdAt();
    $record->updatedAt = $equipment->updatedAt();

    return $record;
  }
  // #endregion
}
