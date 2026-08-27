<?php

declare(strict_types=1);

namespace Facility\Domain\ValueObject;

use Shared\Domain\Exception\InvalidValueException;
use Stringable;

use function mb_strlen;
use function trim;

/**
 * ValueObject FacilityMetadataFieldLabel.
 *
 * The human-readable label shown in the organization's form schema.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FacilityMetadataFieldLabel implements Stringable
{
  // #region Properties
  private string $value;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $value the raw label
   */
  public function __construct(string $value)
  {
    $normalized = trim($value);

    if ('' === $normalized) {
      throw InvalidValueException::because('Facility metadata field label cannot be empty.');
    }

    $length = mb_strlen($normalized);
    if ($length < 2 || $length > 80) {
      throw InvalidValueException::because('Facility metadata field label must be between 2 and 80 characters.');
    }

    $this->value = $normalized;
  }
  // #endregion

  // #region Methods
  /**
   * Method __toString.
   *
   * @since 1.0.0
   *
   * @return string the normalized label
   */
  public function __toString(): string
  {
    return $this->value;
  }
  // #endregion
}
