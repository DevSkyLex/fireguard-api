<?php

declare(strict_types=1);

namespace Organization\Domain\ValueObject;

use Shared\Domain\Exception\InvalidValueException;
use Stringable;

use function mb_strlen;
use function trim;

/**
 * ValueObject OrganizationName.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationName implements Stringable
{
  // #region Properties
  private string $value;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the OrganizationName class.
   *
   * @since 1.0.0
   *
   * @param string $value the raw organization name
   */
  public function __construct(string $value)
  {
    $normalized = trim($value);

    if ('' === $normalized) {
      throw InvalidValueException::because('Organization name cannot be empty.');
    }

    $length = mb_strlen($normalized);
    if ($length < 2 || $length > 120) {
      throw InvalidValueException::because('Organization name must be between 2 and 120 characters.');
    }

    $this->value = $normalized;
  }
  // #endregion

  // #region Methods
  /**
   * Method __toString.
   *
   * Returns the normalized organization name.
   *
   * @since 1.0.0
   *
   * @return string the normalized organization name
   */
  public function __toString(): string
  {
    return $this->value;
  }

  /**
   * Method equals.
   *
   * Checks whether two organization names are equal.
   *
   * @since 1.0.0
   *
   * @param self $other the name to compare
   *
   * @return bool true when equal, false otherwise
   */
  public function equals(self $other): bool
  {
    return $this->value === $other->value;
  }
  // #endregion
}
