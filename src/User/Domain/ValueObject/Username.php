<?php

declare(strict_types=1);

namespace User\Domain\ValueObject;

use Shared\Domain\Exception\InvalidValueException;
use Stringable;

use function preg_match;
use function sprintf;

/**
 * ValueObject Username.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class Username implements Stringable
{
  // #region Constants
  /**
   * Constant PATTERN.
   *
   * Pattern for Username validation.
   * Must be 3-50 characters, alphanumeric with underscores and dashes allowed.
   *
   * @since 1.0.0
   *
   * @var string
   */
  private const string PATTERN = '/^[a-zA-Z0-9_-]{3,50}$/';
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the Username class.
   *
   * @since 1.0.0
   *
   * @param string $value the username value
   *
   * @throws InvalidValueException if the username is invalid
   */
  public function __construct(public string $value)
  {
    if ('' === $value) {
      throw InvalidValueException::because(
        message: 'Username cannot be empty.',
      );
    }

    if (!preg_match(pattern: self::PATTERN, subject: $value)) {
      throw InvalidValueException::because(
        message: sprintf(
          'Invalid username "%s". Must be 3-50 characters, alphanumeric with underscores and dashes allowed.',
          $value,
        ),
      );
    }
  }

  /**
   * Method __toString.
   *
   * Returns the string representation of the Username object.
   *
   * @since 1.0.0
   *
   * @return string the string representation of the Username object
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
   * Compares two Username objects for equality.
   *
   * @since 1.0.0
   *
   * @param self $other the other Username object to compare
   *
   * @return bool true if the objects are equal, false otherwise
   */
  public function equals(self $other): bool
  {
    return $this->value === $other->value;
  }
  // #endregion
}
