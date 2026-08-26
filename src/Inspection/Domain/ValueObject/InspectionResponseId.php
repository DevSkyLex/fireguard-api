<?php

declare(strict_types=1);

namespace Inspection\Domain\ValueObject;

use Shared\Domain\ValueObject\Uuid;

/**
 * ValueObject InspectionResponseId.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InspectionResponseId extends Uuid
{
  // #region Methods
  /**
   * Method fromString.
   *
   * Creates an InspectionResponseId value object from a string.
   *
   * @since 1.0.0
   *
   * @param string $value the UUID value
   *
   * @return self the inspection response identifier
   */
  public static function fromString(string $value): self
  {
    return new self($value);
  }
  // #endregion
}
