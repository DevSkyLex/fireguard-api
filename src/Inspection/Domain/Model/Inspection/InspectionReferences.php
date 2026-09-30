<?php

declare(strict_types=1);

namespace Inspection\Domain\Model\Inspection;

use Inspection\Domain\ValueObject\{InspectionChecklistId, InspectionEquipmentId, InspectionFacilityId, Inspector};

/** Equipment and inspector references for a new or restored inspection. */
final readonly class InspectionReferences
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Captures the inspected equipment, inspector, and optional facility and checklist references.
   *
   * @access public
   *
   * @param InspectionEquipmentId $equipmentId equipment inspected
   * @param Inspector $inspector inspector identity and type
   * @param ?InspectionFacilityId $facilityId facility associated with the inspection, when known
   * @param ?InspectionChecklistId $checklistId checklist used for the inspection, when known
   *
   * @return void
   */
  public function __construct(
    public InspectionEquipmentId $equipmentId,
    public Inspector $inspector,
    public ?InspectionFacilityId $facilityId = null,
    public ?InspectionChecklistId $checklistId = null,
  ) {
  }
  // #endregion
}
