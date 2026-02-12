<?php

declare(strict_types=1);

namespace Facility\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception FacilityCodeAlreadyExistsException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FacilityCodeAlreadyExistsException extends RuntimeException
{
  // #region Methods
  /**
   * Method withCode.
   *
   * Creates an exception for a duplicate facility code in an organization.
   *
   * @since 1.0.0
   *
   * @param string $code the duplicate code
   *
   * @return self the exception instance
   */
  public static function withCode(string $code): self
  {
    return new self(sprintf('Facility code "%s" already exists for this organization.', $code));
  }
  // #endregion
}
