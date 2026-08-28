<?php

declare(strict_types=1);

namespace Facility\Domain\Exception;

use RuntimeException;

/**
 * Exception FacilityAddressNotFoundException.
 *
 * Raised when the geocoding provider knows no match for the submitted
 * address. Maps to a plain 404: an address is not a resource, so no
 * existence oracle is at stake here — unlike
 * {@see FacilityNotFoundException::forOrganizationScope()} the 404 carries
 * no isolation duty, it simply means "no coordinates for that text".
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FacilityAddressNotFoundException extends RuntimeException
{
  // #region Methods
  /**
   * Method forAddress.
   *
   * Creates the exception for an unresolvable address. The address text is
   * deliberately not echoed back — it is user input of up to 300 characters
   * and adds nothing the caller does not already have.
   *
   * @since 1.0.0
   *
   * @return self the exception instance
   */
  public static function forAddress(): self
  {
    return new self('No coordinates found for the given address.');
  }
  // #endregion
}
