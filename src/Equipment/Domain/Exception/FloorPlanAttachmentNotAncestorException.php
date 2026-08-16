<?php

declare(strict_types=1);

namespace Equipment\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception FloorPlanAttachmentNotAncestorException.
 *
 * Raised by `EquipmentFloorPlanValidationPort` when the attachment proposed
 * for a plan position belongs to neither the equipment's own facility nor
 * one of its ancestors. Mapped to HTTP 409 — the request conflicts with the
 * attachment's actual ownership.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FloorPlanAttachmentNotAncestorException extends RuntimeException
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
   * @param string $facilityId the equipment's facility identifier the attachment was proposed for
   *
   * @return self the exception instance
   */
  public static function forAttachment(string $attachmentId, string $facilityId): self
  {
    return new self(sprintf(
      'Attachment "%s" does not belong to facility "%s" or one of its ancestors.',
      $attachmentId,
      $facilityId,
    ));
  }
  // #endregion
}
