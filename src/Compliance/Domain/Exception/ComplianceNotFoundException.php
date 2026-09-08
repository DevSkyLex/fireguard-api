<?php

declare(strict_types=1);

namespace Compliance\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception ComplianceNotFoundException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ComplianceNotFoundException extends RuntimeException
{
  // #region Methods
  /**
   * Method facilityNotFound.
   *
   * Creates an exception for a facility that does not belong to the
   * organization's compliance register (unknown, or in another organization).
   *
   * @since 1.0.0
   *
   * @param string $facilityId the facility identifier
   *
   * @return self the exception instance
   */
  public static function facilityNotFound(string $facilityId): self
  {
    return new self(sprintf('Facility with ID "%s" not found in the compliance register.', $facilityId));
  }

  /**
   * Method snapshotNotFound.
   *
   * Creates an exception for a safety register snapshot that does not exist
   * within the caller's organization — deliberately the same answer for an
   * unknown identifier and for another organization's snapshot, so the
   * response never confirms a foreign snapshot exists.
   *
   * @since 1.1.0
   *
   * @param string $snapshotId the snapshot identifier
   *
   * @return self the exception instance
   */
  public static function snapshotNotFound(string $snapshotId): self
  {
    return new self(sprintf('Safety register snapshot with ID "%s" not found.', $snapshotId));
  }

  /**
   * Method organizationScope.
   *
   * Creates an exception for an organization outside the caller's scope —
   * the same answer an unknown organization identifier produces, so a
   * non-member can never distinguish "exists" from "does not exist".
   *
   * @since 1.1.0
   *
   * @param string $organizationId the organization identifier
   *
   * @return self the exception instance
   */
  public static function organizationScope(string $organizationId): self
  {
    return new self(sprintf('Organization with ID "%s" not found.', $organizationId));
  }
  // #endregion
}
