<?php

declare(strict_types=1);

namespace Facility\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception FacilityNotBuildingException.
 *
 * Raised when the 3D building model is requested for a facility whose type
 * is not `building` — floors and rooms are only meaningful under a building
 * root. Mapped to HTTP 409 at the API boundary: the request conflicts with
 * the facility's own type, mirroring `FacilityAttachmentNotFloorPlanException`.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FacilityNotBuildingException extends RuntimeException
{
  // #region Methods
  /**
   * Method forFacility.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param string $facilityId the facility identifier
   *
   * @return self the exception instance
   */
  public static function forFacility(string $facilityId): self
  {
    return new self(sprintf(
      'Facility "%s" is not a building and has no 3D building model.',
      $facilityId,
    ));
  }
  // #endregion
}
