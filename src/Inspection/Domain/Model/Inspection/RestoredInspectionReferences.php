<?php

declare(strict_types=1);

namespace Inspection\Domain\Model\Inspection;

use Inspection\Domain\ValueObject\{InspectionChecklistId, InspectionEquipmentId, InspectionFacilityId, Inspector};

/** Persisted equipment and inspector references for an inspection. */
final readonly class RestoredInspectionReferences
{
  public function __construct(
    public InspectionEquipmentId $equipmentId,
    public Inspector $inspector,
    public ?InspectionFacilityId $facilityId = null,
    public ?InspectionChecklistId $checklistId = null,
  ) {
  }
}
