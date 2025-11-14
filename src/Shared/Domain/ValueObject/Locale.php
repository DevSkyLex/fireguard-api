<?php

declare(strict_types=1);

namespace Shared\Domain\ValueObject;

use Shared\Domain\Exception\InvalidValueException;
use Stringable;

/**
 * ValueObject Locale
 * @final
 *
 * It represents a locale.
 *
 * @category ValueObject
 * @package Shared\Domain\ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class Locale implements Stringable
{
  //#region Constants
  /**
   * Constant PATTERN
   *
   * The pattern used to validate the locale.
   *
   * @access private
   * @since 1.0.0
   *
   * @var string PATTERN
   */
  private const string PATTERN = '/^[a-z]{2}(?:_[A-Z]{2})?$/';
  //#endregion

  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of
   * the Locale class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $value The locale.
   *
   * @throws InvalidValueException If the locale is invalid.
   */
  public function __construct(public string $value)
  {
    if ($value === '' || !preg_match(self::PATTERN, $value)) {
      throw InvalidValueException::because(message: 'Invalid locale provided.');
    }
  }
  //#endregion

  //#region Methods
  /**
   * Method equals
   * @method equals(self $other): bool
   *
   * Compares two Locale objects for equality.
   *
   * @access public
   * @since 1.0.0
   *
   * @param self $other The other Locale object to compare.
   *
   * @return bool True if the two Locale objects are equal, false otherwise.
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
   * of the Locale object.
   *
   * @access public
   * @since 1.0.0
   *
   * @return string The string representation of the Locale object.
   */
  public function __toString(): string
  {
    return $this->value;
  }
  //#endregion
}
