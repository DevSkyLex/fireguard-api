<?php

declare(strict_types=1);

namespace Facility\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception FacilityMetadataFieldKeyAlreadyExistsException.
 *
 * Raised when a facility metadata field key already exists for the
 * organization. Mapped to HTTP 409 at the API boundary.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FacilityMetadataFieldKeyAlreadyExistsException extends RuntimeException
{
  // #region Methods
  /**
   * Method withKey.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param string $key the duplicate machine key
   *
   * @return self the exception instance
   */
  public static function withKey(string $key): self
  {
    return new self(sprintf('Facility metadata field key "%s" already exists for this organization.', $key));
  }
  // #endregion
}
