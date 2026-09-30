<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Persistence\Doctrine\Mapper;

use Inspection\Domain\Model\Inspection\{Inspection, InspectionFinding, InspectionReferences};
use Inspection\Domain\ValueObject\{
  InspectionChecklistId,
  InspectionEquipmentId,
  InspectionFacilityId,
  InspectionId,
  InspectionOrganizationId,
  InspectionResult,
  InspectionStatus,
  Inspector,
  InspectorType
};
use Inspection\Infrastructure\Persistence\Doctrine\Record\InspectionRecord;
use LogicException;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;

/**
 * Class InspectionMapper
 *
 * Maps inspection records to domain aggregates and aggregate state to records.
 *
 * @category Mapper
 */
final class InspectionMapper
{
  /**
   * Method toDomain.
   *
   * Reconstitutes an inspection aggregate from its record and associated organization.
   *
   * @access public
   *
   * @static
   *
   * @param InspectionRecord $record the persistence record
   *
   * @return Inspection the reconstructed aggregate
   *
   * @throws LogicException when the record has no organization reference
   */
  public static function toDomain(InspectionRecord $record): Inspection
  {
    if (!$record->organization instanceof OrganizationRecord) {
      throw new LogicException('Inspection record must reference an organization.');
    }

    return Inspection::reconstitute(
      id: InspectionId::fromString($record->id),
      organizationId: InspectionOrganizationId::fromString($record->organization->id),
      references: new InspectionReferences(
        equipmentId: InspectionEquipmentId::fromString($record->equipmentId),
        inspector: Inspector::reconstitute(
          type: InspectorType::from($record->inspectorType),
          name: $record->inspectorName,
          userId: $record->inspectorUserId,
          organizationName: $record->inspectorOrganizationName,
        ),
        facilityId: null !== $record->facilityId ? InspectionFacilityId::fromString($record->facilityId) : null,
        checklistId: null !== $record->checklistId ? InspectionChecklistId::fromString($record->checklistId) : null,
      ),
      finding: new InspectionFinding(
        result: InspectionResult::from($record->result),
        status: InspectionStatus::from($record->status),
        performedAt: $record->performedAt,
        notes: $record->notes,
        signature: $record->signature,
      ),
      createdAt: $record->createdAt,
      updatedAt: $record->updatedAt,
    );
  }

  /**
   * Method toRecord.
   *
   * Maps the inspection aggregate's persisted fields to a Doctrine record.
   *
   * @access public
   *
   * @static
   *
   * @param Inspection $inspection the inspection aggregate
   *
   * @return InspectionRecord the persistence record
   */
  public static function toRecord(Inspection $inspection): InspectionRecord
  {
    $record = new InspectionRecord();
    $record->id = (string) $inspection->id();
    $record->equipmentId = (string) $inspection->equipmentId();
    $record->facilityId = $inspection->facilityId()?->__toString();
    $record->inspectorType = $inspection->inspector()->type->value;
    $record->inspectorName = $inspection->inspector()->name;
    $record->inspectorUserId = $inspection->inspector()->userId;
    $record->inspectorOrganizationName = $inspection->inspector()->organizationName;
    $record->result = $inspection->result()->value;
    $record->status = $inspection->status()->value;
    $record->performedAt = $inspection->performedAt();
    $record->checklistId = $inspection->checklistId()?->__toString();
    $record->notes = $inspection->notes();
    $record->signature = $inspection->signature();
    $record->createdAt = $inspection->createdAt();
    $record->updatedAt = $inspection->updatedAt();

    return $record;
  }
}
