<?php

declare(strict_types=1);

namespace Auth\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Class TokenIdentifier
 * @final
 *
 * Value object representing a unique token identifier.
 *
 * @category ValueObject
 * @package Auth\Domain\ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TokenIdentifier
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the
   * TokenIdentifier class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $value The token identifier value.
   *
   * @throws InvalidArgumentException If the value is empty.
   */
  public function __construct(
    public string $value
  ) {
    if ($value === '') throw new InvalidArgumentException(
      message: 'Token identifier cannot be empty'
    );
  }
  //#endregion

  //#region Methods
  /**
   * Method generate
   *
   * Generates a new random token identifier.
   *
   * @access public
   * @since 1.0.0
   *
   * @param positive-int $length The length of the identifier in bytes.
   *
   * @return self The generated identifier.
   */
  public static function generate(int $length = 20): self
  {
    return new self(bin2hex(random_bytes($length)));
  }

  /**
   * Method __toString
   *
   * Returns the string representation.
   *
   * @access public
   * @since 1.0.0
   *
   * @return string The token identifier.
   */
  public function __toString(): string
  {
    return $this->value;
  }

  /**
   * Method equals
   *
   * Checks if this identifier equals another.
   *
   * @access public
   * @since 1.0.0
   *
   * @param self $other The other identifier.
   *
   * @return bool True if equal.
   */
  public function equals(self $other): bool
  {
    return $this->value === $other->value;
  }
  //#endregion
}
