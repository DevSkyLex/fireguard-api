<?php

declare(strict_types=1);

namespace Shared\Domain\ValueObject;

use Stringable;
use Shared\Domain\Exception\InvalidValueException;

/**
 * ValueObject HashedSecret
 * @final
 *
 * It represents a hashed secret.
 *
 * @category ValueObject
 * @package Shared\Domain\ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class HashedSecret implements Stringable
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of
   * the HashedSecret class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $value The hashed secret.
   *
   * @throws InvalidValueException If the hashed secret is invalid.
   */
  public function __construct(public string $value)
  {
    if ($value === '' || str_starts_with($value, '$') === false) {
      throw InvalidValueException::because(message: 'Invalid hashed secret.');
    }
  }
  //#endregion

  //#region Methods
  /**
   * Method equals
   * @method equals(): bool
   *
   * @access public
   * @since 1.0.0
   *
   * @param self $other The other hashed secret to compare.
   *
   * @return bool True if the hashed secrets are equal, false otherwise.
   */
  public function equals(self $other): bool
  {
    return $this->value === $other->value;
  }

  /**
   * Method __toString
   * @method __toString(): string
   *
   * @access public
   * @since 1.0.0
   *
   * @return string The hashed secret as a string.
   */
  public function __toString(): string
  {
    return $this->value;
  }
  //#endregion
}
