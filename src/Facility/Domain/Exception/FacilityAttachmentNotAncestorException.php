<?php

declare(strict_types=1);

namespace Facility\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception FacilityAttachmentNotAncestorException.
 *
 * Raised when a plan geometry is bound to an attachment that belongs to
 * neither the target facility nor one of its ancestors. Mapped to HTTP 409
 * at the API boundary — the request conflicts with the attachment's actual
 * ownership, mirroring `FacilityAttachmentNotFloorPlanException`.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FacilityAttachmentNotAncestorException extends RuntimeException
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
   * @param string $facilityId the facility identifier the geometry was proposed for
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
