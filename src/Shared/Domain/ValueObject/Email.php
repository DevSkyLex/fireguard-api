<?php

declare(strict_types=1);

namespace Shared\Domain\ValueObject;

use Shared\Domain\Exception\DomainException;
use Shared\Domain\Exception\InvalidValueException;
use Stringable;

/**
 * ValueObject Email
 * @final
 *
 * It represents an email address.
 *
 * @category ValueObject
 * @package Shared\Domain\ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class Email implements Stringable
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of
   * the Email class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $value The email address.
   *
   * @throws InvalidValueException If the email address is invalid.
   */
  public function __construct(public string $value)
  {
    if (!filter_var(
      value: $value,
      filter: FILTER_VALIDATE_EMAIL
    )) throw InvalidValueException::because(message: 'Invalid email address.');
  }
  //#endregion

  //#region Methods
  /**
   * Method equals
   * @method equals(self $other): bool
   *
   * Compares two Email objects for equality.
   *
   * @access public
   * @since 1.0.0
   *
   * @param self $other The Email object to compare.
   *
   * @return bool True if the two Email objects are equal, false otherwise.
   */
  public function equals(self $other): bool
  {
    return $this->value === $other->value;
  }

  /**
   * Method __toString
   * @method __toString(): string
   *
   * Returns the string representation
   * of the Email object.
   *
   * @access public
   * @since 1.0.0
   *
   * @return string The string representation of the Email object.
   */
  public function __toString(): string
  {
    return $this->value;
  }
  //#endregion
}
