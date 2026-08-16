<?php

declare(strict_types=1);

namespace Facility\Domain\ValueObject;

use Shared\Domain\ValueObject\Uuid;

/**
 * ValueObject FacilityMetadataFieldId.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FacilityMetadataFieldId extends Uuid
{
  // #region Methods
  /**
   * Method fromString.
   *
   * Creates a FacilityMetadataFieldId value object from a string.
   *
   * @since 1.0.0
   *
   * @param string $value the UUID value
   *
   * @return self the metadata field identifier
   */
  public static function fromString(string $value): self
  {
    return new self($value);
  }
  // #endregion
}
