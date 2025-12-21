<?php

declare(strict_types=1);

namespace Shared\Domain\ValueObject;

use Shared\Domain\Exception\InvalidValueException;
use Stringable;

use function preg_match;

/**
 * ValueObject Uuid.
 *
 * Represents a UUID (Universally Unique Identifier).
 * This is a pure Domain Value Object - UUID generation is handled
 * by UuidFactory in the Application layer to maintain hexagonal purity.
 *
 * @category ValueObject
 *
 * @version 2.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
readonly class Uuid implements Stringable
{
  // #region Constants
  /**
   * Constant PATTERN.
   *
   * The pattern used to validate the UUID (supports v1-v7).
   *
   * @since 1.0.0
   *
   * @var string PATTERN
   */
  private const string PATTERN = '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-7][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$/';
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the Uuid class.
   *
   * @since 1.0.0
   *
   * @param string $value the UUID string
   *
   * @throws InvalidValueException if the UUID format is invalid
   */
  public function __construct(public string $value)
  {
    if ('' === $value || !preg_match(self::PATTERN, $value)) {
      throw InvalidValueException::because(message: 'Invalid UUID provided.');
    }
  }
  // #endregion

  // #region Methods
  /**
   * Method equals.
   *
   * Compares two Uuid objects for equality.
   *
   * @since 1.0.0
   *
   * @param self $other the other Uuid object to compare
   *
   * @return bool true if the two Uuid objects are equal, false otherwise
   */
  public function equals(self $other): bool
  {
    return $this->value === $other->value;
  }

  /**
   * Method __toString.
   *
   * Returns the string representation
   * of the Uuid object.
   *
   * @since 1.0.0
   *
   * @return string the string representation of the Uuid object
   */
  public function __toString(): string
  {
    return $this->value;
  }
  // #endregion
}
