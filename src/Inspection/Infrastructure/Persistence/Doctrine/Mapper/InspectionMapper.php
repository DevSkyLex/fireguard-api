<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Persistence\Doctrine\Mapper;

use Inspection\Domain\Model\Inspection\Inspection;
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

final class InspectionMapper
{
  public static function toDomain(InspectionRecord $record): Inspection
  {
    return Inspection::reconstitute(
      id: InspectionId::fromString($record->id),
      organizationId: InspectionOrganizationId::fromString($record->organizationId),
      equipmentId: InspectionEquipmentId::fromString($record->equipmentId),
      inspector: Inspector::reconstitute(
        type: InspectorType::from($record->inspectorType),
        name: $record->inspectorName,
        userId: $record->inspectorUserId,
        organizationName: $record->inspectorOrganizationName,
      ),
      result: InspectionResult::from($record->result),
      status: InspectionStatus::from($record->status),
      performedAt: $record->performedAt,
      createdAt: $record->createdAt,
      updatedAt: $record->updatedAt,
      facilityId: null !== $record->facilityId ? InspectionFacilityId::fromString($record->facilityId) : null,
      checklistId: null !== $record->checklistId ? InspectionChecklistId::fromString($record->checklistId) : null,
      notes: $record->notes,
      signature: $record->signature,
    );
  }

  public static function toRecord(Inspection $inspection): InspectionRecord
  {
    $record = new InspectionRecord();
    $record->id = (string) $inspection->id();
    $record->organizationId = (string) $inspection->organizationId();
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
