<?php

declare(strict_types=1);

namespace OAuth\Domain\ValueObject\Token;

use InvalidArgumentException;

use function bin2hex;
use function random_bytes;

/**
 * Class TokenIdentifier.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TokenIdentifier
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * TokenIdentifier class.
   *
   * @since 1.0.0
   *
   * @param string $value the token identifier value
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

  /**
   * Method __toString.
   *
   * Returns the string representation.
   *
   * @since 1.0.0
   *
   * @return string the token identifier
   */
  public function __toString(): string
  {
    return $this->value;
  }
  // #endregion

  // #region Methods
  /**
   * Method generate.
   *
   * Generates a new random token
   * identifier.
   *
   * @since 1.0.0
   *
   * @param positive-int $length the length of the identifier in bytes
   *
   * @return self the generated identifier
   */
  public static function generate(int $length = 20): self
  {
    return new self(bin2hex(random_bytes($length)));
  }

  /**
   * Method equals.
   *
   * Checks if this identifier
   * equals another.
   *
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
