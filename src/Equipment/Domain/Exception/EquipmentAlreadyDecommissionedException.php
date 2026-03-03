<?php

declare(strict_types=1);

namespace Equipment\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception EquipmentAlreadyDecommissionedException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class EquipmentAlreadyDecommissionedException extends RuntimeException
{
  // #region Methods
  /**
   * Method withId.
   *
   * Creates an exception for an already decommissioned equipment.
   *
   * @since 1.0.0
   *
   * @param string $id the equipment identifier
   *
   * @return self the exception instance
   */
  public static function withId(string $id): self
  {
    return new self(sprintf('Equipment with ID "%s" is already decommissioned.', $id));
  }
  // #endregion
}
