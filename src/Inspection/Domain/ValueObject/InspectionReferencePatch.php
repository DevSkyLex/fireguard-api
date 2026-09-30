<?php

declare(strict_types=1);

namespace Inspection\Domain\ValueObject;

/**
 * References supplied for one draft inspection edit.
 *
 * Presence is independent of value: an explicit null clears the optional
 * facility or checklist, while an absent field keeps its persisted value.
 *
 * @category ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InspectionReferencePatch
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Carries linked resource identifiers and presence flags for a draft update.
   *
   * @access public
   *
   * @param ?InspectionEquipmentId $equipmentId replacement equipment reference, when supplied
   * @param bool $hasEquipmentId whether the equipment reference was included
   * @param ?InspectionFacilityId $facilityId replacement optional facility reference
   * @param bool $hasFacilityId whether the facility reference was included
   * @param ?InspectionChecklistId $checklistId replacement optional checklist reference
   * @param bool $hasChecklistId whether the checklist reference was included
   *
   * @return void
   */
  public function __construct(
    public ?InspectionEquipmentId $equipmentId = null,
    public bool $hasEquipmentId = false,
    public ?InspectionFacilityId $facilityId = null,
    public bool $hasFacilityId = false,
    public ?InspectionChecklistId $checklistId = null,
    public bool $hasChecklistId = false,
  ) {
  }
  // #endregion
}
