<?php

declare(strict_types=1);

namespace Shared\Domain\ValueObject;

use Shared\Domain\Exception\InvalidValueException;
use Stringable;

/**
 * ValueObject ClientId
 * @final
 *
 * It represents a client identifier.
 *
 * @category ValueObject
 * @package Shared\Domain\ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ClientId implements Stringable
{
  //#region Constants
  /**
   * Constant PATTERN
   *
   * The pattern used to validate the
   * client identifier.
   *
   * @access private
   * @since 1.0.0
   *
   * @var string PATTERN
   */
  private const string PATTERN = '/^[a-zA-Z0-9][a-zA-Z0-9._-]{2,127}$/';
  //#endregion

  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of
   * the ClientId class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $value The client identifier.
   *
   * @throws InvalidValueException If the client identifier is invalid.
   */
  public function __construct(public string $value)
  {
    if ($value === '' || !preg_match(pattern: self::PATTERN, subject: $value)) {
      throw InvalidValueException::because(message: 'Invalid client identifier.');
    }
  }

  /**
   * Method equals
   *
   * Compares two ClientId objects for equality.
   *
   * @access public
   * @since 1.0.0
   *
   * @param self $other The other ClientId object to compare.
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
   * Returns the string representation of the ClientId object.
   *
   * @access public
   * @since 1.0.0
   *
   * @return string The string representation of the ClientId object.
   */
  public function __toString(): string
  {
    return $this->value;
  }
  //#endregion
}
