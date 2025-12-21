<?php

declare(strict_types=1);

namespace Authorization\Domain\ValueObject;

use Shared\Domain\Exception\InvalidValueException;
use Stringable;

use function preg_match;
use function sprintf;

/**
 * ValueObject RoleName.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RoleName implements Stringable
{
  // #region Constants
  /**
   * Constant PATTERN.
   *
   * Pattern for Role name
   *
   * @since 1.0.0
   *
   * @var string
   */
  private const string PATTERN = '/^[a-z0-9_]{3,50}$/';
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the RoleName class.
   *
   * @since 1.0.0
   *
   * @param string $value the role name value
   *
   * @throws InvalidValueException if the role name is invalid
   */
  public function __construct(public string $value)
  {
    if ('' === $value) {
      throw InvalidValueException::because(
        message: 'Role name cannot be empty.',
      );
    }

    if (!preg_match(pattern: self::PATTERN, subject: $value)) {
      throw InvalidValueException::because(
        message: sprintf(
          'Invalid role name "%s". Must be 3-50 characters, lowercase alphanumeric with underscores allowed.',
          $value,
        ),
      );
    }
  }
  // #endregion

  // #region Methods
  /**
   * Method equals.
   *
   * Compares two RoleName objects for equality.
   *
   * @since 1.0.0
   *
   * @param self $other the other RoleName object to compare
   *
   * @return bool true if the objects are equal, false otherwise
   */
  public function equals(self $other): bool
  {
    return $this->value === $other->value;
  }

  /**
   * Method __toString.
   *
   * Returns the string representation of the RoleName object.
   *
   * @since 1.0.0
   *
   * @return string the string representation of the RoleName object
   */
  public function __toString(): string
  {
    return $this->value;
  }
  // #endregion
}
