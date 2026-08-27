<?php

declare(strict_types=1);

namespace Facility\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception FacilityAttachmentNotFloorPlanException.
 *
 * Raised when an attachment that is not a floor plan (kind `document`) is
 * asked to become the facility's primary plan. Mapped to HTTP 409 at the API
 * boundary — the request conflicts with the attachment's own kind, mirroring
 * `FacilityHasActiveDependentsException`.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FacilityAttachmentNotFloorPlanException extends RuntimeException
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
    return new self(sprintf(
      'Attachment "%s" is not a floor plan and cannot be set as the primary plan.',
      $attachmentId,
    ));
  }
  // #endregion
}
