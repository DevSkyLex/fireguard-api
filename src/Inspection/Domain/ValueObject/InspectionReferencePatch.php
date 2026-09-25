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
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InspectionReferencePatch
{
  public function __construct(
    public ?InspectionEquipmentId $equipmentId = null,
    public bool $hasEquipmentId = false,
    public ?InspectionFacilityId $facilityId = null,
    public bool $hasFacilityId = false,
    public ?InspectionChecklistId $checklistId = null,
    public bool $hasChecklistId = false,
  ) {
  }
}
