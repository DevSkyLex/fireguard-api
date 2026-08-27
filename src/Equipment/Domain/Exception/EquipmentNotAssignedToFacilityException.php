<?php

declare(strict_types=1);

namespace Equipment\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception EquipmentNotAssignedToFacilityException.
 *
 * Raised when a plan position is requested for equipment that carries no
 * `facilityId` — there is no facility whose floor plan the point could be
 * validated against. Mapped to HTTP 409 at the API boundary: the request
 * conflicts with the equipment's current assignment state.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class EquipmentNotAssignedToFacilityException extends RuntimeException
{
  // #region Methods
  /**
   * Method withId.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param string $id the equipment identifier
   *
   * @return self the exception instance
   */
  public static function withId(string $id): self
  {
    return new self(sprintf('Equipment with ID "%s" is not assigned to a facility and cannot be placed on a plan.', $id));
  }
  // #endregion
}
