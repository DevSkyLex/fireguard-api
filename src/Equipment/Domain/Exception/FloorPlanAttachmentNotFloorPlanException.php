<?php

declare(strict_types=1);

namespace Equipment\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception FloorPlanAttachmentNotFloorPlanException.
 *
 * Raised by `EquipmentFloorPlanValidationPort` when the attachment proposed
 * for a plan position exists but is not a floor plan. Mapped to HTTP 409 —
 * the request conflicts with the attachment's own kind.
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
