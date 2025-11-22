<?php

declare(strict_types=1);

namespace User\Domain\ValueObject;

use Shared\Domain\Exception\InvalidValueException;
use Stringable;

use function sprintf;

/**
 * ValueObject Username
 * @final
 *
 * Represents a user's username.
 * Must be 3-50 characters, alphanumeric with underscores and dashes allowed.
 *
 * @category ValueObject
 * @package User\Domain\ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class Username implements Stringable
{
  //#region Constants
  /**
   * Constant PATTERN
   * 
   * Pattern for Username validation.
   * Must be 3-50 characters, alphanumeric with underscores and dashes allowed.
   * 
   * @access private
   * @since 1.0.0
   * 
   * @var string
   */
  private const string PATTERN = '/^[a-zA-Z0-9_-]{3,50}$/';
  //#endregion

  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the Username class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $value The username value.
   *
   * @throws InvalidValueException If the username is invalid.
   */
  public function __construct(public string $value)
  {
    if ($value === '') {
      throw InvalidValueException::because(
        message: 'Username cannot be empty.'
      );
    }

    if (!preg_match(pattern: self::PATTERN, subject: $value)) {
      throw InvalidValueException::because(
        message: sprintf(
          'Invalid username "%s". Must be 3-50 characters, alphanumeric with underscores and dashes allowed.',
          $value
        )
      );
    }
  }
  //#endregion

  //#region Methods
  /**
   * Method equals
   *
   * Compares two Username objects for equality.
   *
   * @access public
   * @since 1.0.0
   *
   * @param self $other The other Username object to compare.
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
   * Returns the string representation of the Username object.
   *
   * @access public
   * @since 1.0.0
   *
   * @return string The string representation of the Username object.
   */
  public function __toString(): string
  {
    return $this->value;
  }
  //#endregion
}
