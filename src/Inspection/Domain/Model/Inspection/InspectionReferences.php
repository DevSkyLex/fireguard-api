<?php

declare(strict_types=1);

namespace Inspection\Domain\Model\Inspection;

use Inspection\Domain\ValueObject\{InspectionChecklistId, InspectionEquipmentId, InspectionFacilityId, Inspector};

/** Equipment and inspector references for a new or restored inspection. */
final readonly class InspectionReferences
{
  public function __construct(
    public InspectionEquipmentId $equipmentId,
    public Inspector $inspector,
    public ?InspectionFacilityId $facilityId = null,
    public ?InspectionChecklistId $checklistId = null,
  ) {
  }
}
