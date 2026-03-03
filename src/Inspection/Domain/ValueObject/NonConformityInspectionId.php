<?php

declare(strict_types=1);

namespace Inspection\Domain\ValueObject;

use Shared\Domain\ValueObject\Uuid;

/**
 * ValueObject NonConformityInspectionId.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class NonConformityInspectionId extends Uuid
{
  // #region Methods
  /**
   * Method fromString.
   *
   * Creates a NonConformityInspectionId value object from a string.
   *
   * @since 1.0.0
   *
   * @param string $value the UUID value
   *
   * @return self the inspection identifier
   */
  public static function fromString(string $value): self
  {
    return new self($value);
  }
  // #endregion
}
