<?php

declare(strict_types=1);

namespace Equipment\Application\Contract\FloorPlan;

use RuntimeException;

use function sprintf;

/**
 * Contract exception FloorPlanAttachmentNotFloorPlanException.
 *
 * Raised by `EquipmentFloorPlanValidationPort` when the attachment proposed
 * for a plan position exists but is not a floor plan. Mapped to HTTP 409 —
 * the request conflicts with the attachment's own kind. Lives on the
 * contract surface because it crosses the module boundary: the Facility
 * adapter implementing the port throws it, and only `Application\Contract\`
 * types may be imported by a sibling module.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FloorPlanAttachmentNotFloorPlanException extends RuntimeException
{
  // #region Methods
  /**
   * Method forAttachment.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param string $attachmentId the attachment identifier
   *
   * @return self the exception instance
   */
  public static function forAttachment(string $attachmentId): self
  {
    return new self(sprintf('Attachment "%s" is not a floor plan and cannot be used to place equipment.', $attachmentId));
  }
  // #endregion
}
