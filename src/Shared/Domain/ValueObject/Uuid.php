<?php

declare(strict_types=1);

namespace Shared\Domain\ValueObject;

use Shared\Domain\Exception\InvalidValueException;
use Stringable;

/**
 * ValueObject Uuid
 * @final
 *
 * It represents a UUID.
 *
 * @category ValueObject
 * @package Shared\Domain\ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class Uuid implements Stringable
{
  //#region Constants
  /**
   * Constant PATTERN
   *
   * The pattern used to validate the UUID.
   *
   * @access private
   * @since 1.0.0
   *
   * @var string PATTERN
   */
  private const string PATTERN = '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$/';
  //#endregion

  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of
   * the Uuid class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $value The UUID.
   *
   * @throws InvalidValueException If the UUID is invalid.
   */
  public function __construct(public string $value)
  {
    if ($value === '' || !preg_match(self::PATTERN, $value)) {
      throw InvalidValueException::because(message: 'Invalid UUID provided.');
    }
  }
  //#endregion

  //#region Methods
  /**
   * Method equals
   *
   * Compares two Uuid objects for equality.
   *
   * @access public
   * @since 1.0.0
   *
   * @param self $other The other Uuid object to compare.
   *
   * @return bool True if the two Uuid objects are equal, false otherwise.
   */
  public function equals(self $other): bool
  {
    return $this->value === $other->value;
  }

  /**
   * Method __toString
   *
   * Returns the string representation
   * of the Uuid object.
   *
   * @access public
   * @since 1.0.0
   *
   * @return string The string representation of the Uuid object.
   */
  public function __toString(): string
  {
    return $this->value;
  }
  //#endregion
}
