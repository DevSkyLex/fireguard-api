<?php

declare(strict_types=1);

namespace Equipment\Application\Port\Outbound;

use Equipment\Domain\Exception\{
  FloorPlanAttachmentNotAncestorException,
  FloorPlanAttachmentNotFloorPlanException,
  FloorPlanAttachmentNotFoundException
};

/**
 * Port EquipmentFloorPlanValidationPort.
 *
 * Validates, on behalf of `SetEquipmentPlanPositionHandler`, that an
 * attachment is a floor plan belonging to the given facility (the
 * equipment's own assignment) or one of its ancestors — the business rule
 * Equipment cannot verify itself without importing Facility's Domain or
 * Infrastructure. Implemented by the Facility module, mirroring the
 * direction of `FacilityValidationPort`.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface EquipmentFloorPlanValidationPort
{
  // #region Methods
  /**
   * Method assertAttachmentUsableForFacility.
   *
   * @since 1.0.0
   *
   * @param string $attachmentId the floor plan attachment identifier
   * @param string $facilityId the facility identifier the attachment must belong to (or be an ancestor of)
   *
   * @throws FloorPlanAttachmentNotFoundException when the attachment does not exist
   * @throws FloorPlanAttachmentNotFloorPlanException when the attachment is not a floor plan
   * @throws FloorPlanAttachmentNotAncestorException when the attachment belongs to neither the facility nor an ancestor
   */
  public function assertAttachmentUsableForFacility(string $attachmentId, string $facilityId): void;
  // #endregion
}
