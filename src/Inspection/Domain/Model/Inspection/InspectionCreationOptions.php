<?php

declare(strict_types=1);

namespace Inspection\Domain\Model\Inspection;

use Inspection\Domain\ValueObject\{InspectionChecklistId, InspectionFacilityId};

/**
 * Optional references and text supplied when a draft inspection is created.
 *
 * @category ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InspectionCreationOptions
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Groups optional facility, checklist, notes, and signature values supplied when creating an inspection.
   *
   * @access public
   *
   * @param ?InspectionFacilityId $facilityId facility where the inspection was performed, when supplied
   * @param ?InspectionChecklistId $checklistId checklist used to conduct the inspection, when supplied
   * @param ?string $notes optional notes recorded at creation
   * @param ?string $signature optional inspector signature data
   *
   * @return void
   */
  public function __construct(
    public ?InspectionFacilityId $facilityId = null,
    public ?InspectionChecklistId $checklistId = null,
    public ?string $notes = null,
    public ?string $signature = null,
  ) {
  }
  // #endregion
}
