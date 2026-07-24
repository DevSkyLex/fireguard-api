<?php

declare(strict_types=1);

namespace Facility\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception FacilityHasActiveDependentsException.
 *
 * Raised when a facility cannot be archived because it still has active
 * dependents (child facilities, equipment, or inspections) that archiving would
 * orphan. Mapped to HTTP 409 at the API boundary so the caller can resolve the
 * dependents first.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FacilityHasActiveDependentsException extends RuntimeException
{
  // #region Methods
  /**
   * Method withActiveChildFacilities.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param string $facilityId the facility identifier
   *
   * @return self the exception instance
   */
  public static function withActiveChildFacilities(string $facilityId): self
  {
    return new self(sprintf(
      'Facility "%s" cannot be archived while it has active child facilities; archive or move them first.',
      $facilityId,
    ));
  }

  /**
   * Method withActiveEquipment.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param string $facilityId the facility identifier
   *
   * @return self the exception instance
   */
  public static function withActiveEquipment(string $facilityId): self
  {
    return new self(sprintf(
      'Facility "%s" cannot be archived while it has active equipment assigned; decommission or reassign it first.',
      $facilityId,
    ));
  }

  /**
   * Method withActiveInspections.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param string $facilityId the facility identifier
   *
   * @return self the exception instance
   */
  public static function withActiveInspections(string $facilityId): self
  {
    return new self(sprintf(
      'Facility "%s" cannot be archived while it has in-progress inspections; close or cancel them first.',
      $facilityId,
    ));
  }
  // #endregion
}
