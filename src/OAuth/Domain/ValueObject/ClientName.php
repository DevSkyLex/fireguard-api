<?php

declare(strict_types=1);

namespace OAuth\Domain\ValueObject;

use Shared\Domain\Exception\InvalidValueException;
use Stringable;

use function mb_strlen;
use function sprintf;
use function trim;

/**
 * ValueObject ClientName.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ClientName implements Stringable
{
  // #region Constants
  /**
   * Constant MIN_LENGTH.
   *
   * Minimum length for a client name.
   *
   * @since 1.0.0
   *
   * @var int MIN_LENGTH
   */
  private const int MIN_LENGTH = 3;

  /**
   * Constant MAX_LENGTH.
   *
   * Maximum length for a client name.
   *
   * @since 1.0.0
   *
   * @var int MAX_LENGTH
   */
  private const int MAX_LENGTH = 100;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the ClientName class.
   *
   * @since 1.0.0
   *
   * @param string $value the client name
   *
   * @throws InvalidValueException if the client name is invalid
   */
  public function __construct(public string $value)
  {
    $trimmed = trim($value);
    $length = mb_strlen($trimmed);

    if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
      throw InvalidValueException::because(
        message: sprintf(
          'Client name must be between %d and %d characters.',
          self::MIN_LENGTH,
          self::MAX_LENGTH,
        ),
      );
    }

    // Note: Cannot reassign readonly property, validation only
  }

  /**
   * Method __toString.
   *
   * Returns the string representation of the ClientName object.
   *
   * @since 1.0.0
   *
   * @return string the string representation of the ClientName object
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
   * Compares two ClientName objects for equality.
   *
   * @since 1.0.0
   *
   * @param self $other the other ClientName object to compare
   *
   * @return bool true if the objects are equal, false otherwise
   */
  public function equals(self $other): bool
  {
    return $this->value === $other->value;
  }
  // #endregion
}
