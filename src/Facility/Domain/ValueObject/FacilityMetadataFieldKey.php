<?php

declare(strict_types=1);

namespace Facility\Domain\ValueObject;

use Shared\Domain\Exception\InvalidValueException;
use Stringable;

use function mb_strlen;
use function preg_match;
use function trim;

/**
 * ValueObject FacilityMetadataFieldKey.
 *
 * The machine key an organization uses to reference one of its metadata
 * field definitions, and the key looked up in a facility's free-form
 * `metadata` map. Kebab-case or snake_case only, lowercase, so it never
 * collides with a legacy free-form key entered by a human.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FacilityMetadataFieldKey implements Stringable
{
  // #region Constants
  private const string PATTERN = '/^[a-z0-9]+([_-][a-z0-9]+)*$/';
  // #endregion

  // #region Properties
  private string $value;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $value the raw machine key
   */
  public function __construct(string $value)
  {
    $normalized = trim($value);

    if ('' === $normalized) {
      throw InvalidValueException::because('Facility metadata field key cannot be empty.');
    }

    $length = mb_strlen($normalized);
    if ($length < 2 || $length > 64) {
      throw InvalidValueException::because('Facility metadata field key must be between 2 and 64 characters.');
    }

    if (1 !== preg_match(self::PATTERN, $normalized)) {
      throw InvalidValueException::because('Facility metadata field key must be lowercase kebab-case or snake_case (e.g. "surface-m2", "occupancy_load").');
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
   * @return string the normalized machine key
   */
  public function __toString(): string
  {
    return $this->value;
  }

  /**
   * Method equals.
   *
   * @since 1.0.0
   *
   * @param self $other the key to compare
   *
   * @return bool true when equal, false otherwise
   */
  public function equals(self $other): bool
  {
    return $this->value === $other->value;
  }
  // #endregion
}
