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
  // #endregion
}
