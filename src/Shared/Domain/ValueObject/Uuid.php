<?php

declare(strict_types=1);

namespace Shared\Domain\ValueObject;

use Shared\Domain\Exception\InvalidValueException;
use Stringable;

use function vsprintf;
use function bin2hex;
use function random_bytes;
use function ord;
use function chr;
use function preg_match;
use function str_split;

/**
 * ValueObject Uuid
 *
 * It represents a UUID.
 *
 * @category ValueObject
 * @package Shared\Domain\ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
readonly class Uuid implements Stringable
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
  private const string PATTERN = '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-7][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$/';
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
   * Method generate
   *
   * Generates a new UUID v7.
   *
   * @access public
   * @since 1.0.0
   *
   * @return self A new Uuid instance.
   */
  public static function generate(): self
  {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10

    return new self(value: vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4)));
  }

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
