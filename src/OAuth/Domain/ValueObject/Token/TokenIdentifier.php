<?php

declare(strict_types=1);

namespace OAuth\Domain\ValueObject\Token;

use InvalidArgumentException;

use function bin2hex;
use function random_bytes;

/**
 * Class TokenIdentifier
 *
 * Identifies a token and provides operations for generating and comparing identifiers.
 *
 * @category ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TokenIdentifier
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Stores a non-empty token identifier.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $value the token identifier value
   *
   * @return void
   *
   * @throws InvalidArgumentException if the value is empty
   */
  public function __construct(
    public string $value,
  ) {
    if ('' === $value) {
      throw new InvalidArgumentException(
        message: 'Token identifier cannot be empty',
      );
    }
  }
  // #endregion

  // #region Methods
  /**
   * Method __toString
   *
   * Returns the stored token identifier as a string.
   *
   * @access public
   * @since 1.0.0
   *
   * @return string the token identifier
   */
  public function __toString(): string
  {
    return $this->value;
  }

  /**
   * Method generate
   *
   * Generates an identifier by hex-encoding random bytes.
   *
   * @access public
   * @since 1.0.0
   *
   * @param positive-int $length the number of random bytes to encode
   *
   * @return self the generated identifier
   */
  public static function generate(int $length = 20): self
  {
    return new self(bin2hex(random_bytes($length)));
  }

  /**
   * Method equals
   *
   * Compares this identifier with another identifier.
   *
   * @access public
   * @since 1.0.0
   *
   * @param self $other the other identifier
   *
   * @return bool true if equal
   */
  public function equals(self $other): bool
  {
    return $this->value === $other->value;
  }
  // #endregion
}
