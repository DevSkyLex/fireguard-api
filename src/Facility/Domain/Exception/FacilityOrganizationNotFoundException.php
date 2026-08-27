<?php

declare(strict_types=1);

namespace Facility\Domain\Exception;

use InvalidArgumentException;

/**
 * Exception FacilityOrganizationNotFoundException.
 *
 * Raised when persisting a facility whose owning organization does not exist.
 * The persistence layer detects the condition and translates it here, so the
 * Application layer never handles a driver-specific constraint exception.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FacilityOrganizationNotFoundException extends InvalidArgumentException
{
  // #region Methods
  /**
   * Method create.
   *
   * @since 1.0.0
   */
  public static function create(): self
  {
    return new self('Organization not found.');
  }
  // #endregion
}
