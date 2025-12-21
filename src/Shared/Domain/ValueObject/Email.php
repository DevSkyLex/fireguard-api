<?php

declare(strict_types=1);

namespace Shared\Domain\ValueObject;

use Shared\Domain\Exception\InvalidValueException;
use Stringable;

use function filter_var;

use const FILTER_VALIDATE_EMAIL;

/**
 * ValueObject Email.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class Email implements Stringable
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of
   * the Email class.
   *
   * @since 1.0.0
   *
   * @param string $value the email address
   *
   * @throws InvalidValueException if the email address is invalid
   */
  public function __construct(public string $value)
  {
    if (!filter_var(
      value: $value,
      filter: FILTER_VALIDATE_EMAIL,
    )) {
      throw InvalidValueException::because(message: 'Invalid email address.');
    }
  }

  /**
   * Method __toString.
   *
   * Returns the string representation
   * of the Email object.
   *
   * @since 1.0.0
   *
   * @return string the string representation of the Email object
   */
  public function __toString(): string
  {
    return $this->value;
  }
  // #endregion

  // #region Methods
  /**
   * Method equals.
   *
   * Compares two Email objects for equality.
   *
   * @since 1.0.0
   *
   * @param self $other the Email object to compare
   *
   * @return bool true if the two Email objects are equal, false otherwise
   */
  public function equals(self $other): bool
  {
    return $this->value === $other->value;
  }
  // #endregion
}
