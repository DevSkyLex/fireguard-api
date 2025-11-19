<?php

declare(strict_types=1);

namespace Shared\Domain\ValueObject;

use Shared\Domain\Exception\InvalidValueException;
use Stringable;

/**
 * ValueObject PhoneNumber
 * @final
 *
 * Represents a phone number in E.164 format.
 * Useful for 2FA, SMS notifications, contact information.
 *
 * @category ValueObject
 * @package Shared\Domain\ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PhoneNumber implements Stringable
{
  //#region Constants
  /**
   * Constant PATTERN
   *
   * The pattern used to validate the phone number (E.164 format).
   * Format: +[country code][number] (e.g., +33612345678)
   *
   * @access private
   * @since 1.0.0
   *
   * @var string PATTERN
   */
  private const string PATTERN = '/^\+[1-9]\d{1,14}$/';
  //#endregion

  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the PhoneNumber class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $value The phone number in E.164 format.
   *
   * @throws InvalidValueException If the phone number is invalid.
   */
  public function __construct(public string $value)
  {
    if (!preg_match(pattern: self::PATTERN, subject: $value)) {
      throw InvalidValueException::because(
        message: 'Invalid phone number format. Expected E.164 format (e.g., +33612345678).'
      );
    }
  }
  //#endregion

  //#region Methods
  /**
   * Method getCountryCode
   *
   * Extracts the country code from the phone number.
   *
   * @access public
   * @since 1.0.0
   *
   * @return string The country code (e.g., "33" for France).
   */
  public function getCountryCode(): string
  {
    // Extract country code (1-3 digits after +)
    preg_match(pattern: '/^\+(\d{1,3})/', subject: $this->value, matches: $matches);
    return $matches[1] ?? '';
  }

  /**
   * Method getNationalNumber
   *
   * Returns the national number without country code.
   *
   * @access public
   * @since 1.0.0
   *
   * @return string The national number.
   */
  public function getNationalNumber(): string
  {
    $countryCode = $this->getCountryCode();
    return substr(string: $this->value, offset: strlen(string: '+' . $countryCode));
  }

  /**
   * Method equals
   *
   * Compares two PhoneNumber objects for equality.
   *
   * @access public
   * @since 1.0.0
   *
   * @param self $other The other PhoneNumber object to compare.
   *
   * @return bool True if the objects are equal, false otherwise.
   */
  public function equals(self $other): bool
  {
    return $this->value === $other->value;
  }

  /**
   * Method __toString
   *
   * Returns the string representation of the PhoneNumber object.
   *
   * @access public
   * @since 1.0.0
   *
   * @return string The string representation of the PhoneNumber object.
   */
  public function __toString(): string
  {
    return $this->value;
  }
  //#endregion
}
