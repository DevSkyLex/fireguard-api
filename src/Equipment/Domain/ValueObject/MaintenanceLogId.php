<?php

declare(strict_types=1);

namespace Equipment\Domain\ValueObject;

use Shared\Domain\ValueObject\Uuid;

/**
 * ValueObject MaintenanceLogId.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MaintenanceLogId extends Uuid
{
  // #region Methods
  /**
   * Method fromString.
   *
   * Creates a MaintenanceLogId value object from a string.
   *
   * @since 1.0.0
   *
   * @param string $value the UUID value
   *
   * @return self the maintenance log identifier
   */
  public static function fromString(string $value): self
  {
    return new self($value);
  }
  // #endregion
}
