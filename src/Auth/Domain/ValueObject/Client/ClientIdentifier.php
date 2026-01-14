<?php

declare(strict_types=1);

namespace Auth\Domain\ValueObject\Client;

use Auth\Domain\Exception\Client\InvalidClientIdentifierException;
use Stringable;

use function preg_match;

/**
 * ValueObject ClientIdentifier.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ClientIdentifier implements Stringable
{
  // #region Constants
  /**
   * Constant PATTERN.
   *
   * The pattern used to validate the client identifier.
   * Must be 3-128 characters long, start with alphanumeric,
   * and contain only alphanumeric, dots, hyphens, and underscores.
   *
   * @since 1.0.0
   *
   * @var string PATTERN
   */
  private const string PATTERN = '/^[a-zA-Z0-9][a-zA-Z0-9._-]{2,127}$/';
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * ClientIdentifier class.
   *
   * @since 1.0.0
   *
   * @param string $value the client identifier
   *
   * @throws InvalidClientIdentifierException if the client identifier is invalid
   */
  public function __construct(public string $value)
  {
    if ('' === $value) {
      throw InvalidClientIdentifierException::empty();
    }

    if (!preg_match(pattern: self::PATTERN, subject: $value)) {
      throw InvalidClientIdentifierException::invalidPattern(
        value: $value,
      );
    }
  }

  /**
   * Method __toString.
   *
   * Returns the string representation of the
   * ClientIdentifier object.
   *
   * @since 1.0.0
   *
   * @return string the string representation of the ClientIdentifier object
   */
  public function __toString(): string
  {
    return $this->value;
  }

  /**
   * Method equals.
   *
   * Compares two ClientIdentifier
   * objects for equality.
   *
   * @since 1.0.0
   *
   * @param self $other the other ClientIdentifier object to compare
   *
   * @return bool true if the objects are equal, false otherwise
   */
  public function equals(self $other): bool
  {
    return $this->value === $other->value;
  }
  // #endregion
}
