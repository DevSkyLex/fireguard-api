<?php

declare(strict_types=1);

namespace Equipment\Domain\Exception;

use RuntimeException;

/**
 * Class EquipmentReplacementConflictException
 *
 * Refuses a replacement whose lifecycle or replay identity is incompatible.
 *
 * @category Exception
 */
final class EquipmentReplacementConflictException extends RuntimeException
{
  // #region Methods
  /**
   * Method because
   *
   * @access public
   *
   * @param string $reason the rejected invariant
   *
   * @return self the conflict
   */
  public static function because(string $reason): self
  {
    return new self($reason);
  }
  // #endregion
}
