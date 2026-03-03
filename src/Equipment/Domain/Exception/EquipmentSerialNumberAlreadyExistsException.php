<?php

declare(strict_types=1);

namespace Equipment\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception EquipmentSerialNumberAlreadyExistsException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class EquipmentSerialNumberAlreadyExistsException extends RuntimeException
{
  // #region Methods
  /**
   * Method withSerialNumber.
   *
   * Creates an exception for a duplicate serial number within an organization.
   *
   * @since 1.0.0
   *
   * @param string $serialNumber the duplicate serial number
   *
   * @return self the exception instance
   */
  public static function withSerialNumber(string $serialNumber): self
  {
    return new self(sprintf('Serial number "%s" already exists in this organization.', $serialNumber));
  }
  // #endregion
}
