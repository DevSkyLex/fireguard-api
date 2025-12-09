<?php

declare(strict_types=1);

namespace Otp\Domain\ValueObject;

use Shared\Domain\ValueObject\Uuid;

/**
 * ValueObject ChallengeToken
 * @final
 *
 * A secure token used to identify an OTP challenge in API responses.
 * This is exposed publicly instead of the internal OTP ID for security.
 *
 * @category ValueObject
 * @package Otp\Domain\ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ChallengeToken
{
  //#region Properties
  /**
   * Property value
   *
   * The token value.
   * 
   * @access public
   * @since 1.0.0
   *
   * @var string
   */
  public string $value;
  //#endregion

  //#region Constructor
  /**
   * Constructor
   *
   * @access private
   * @since 1.0.0
   *
   * @param string $value The token value.
   */
  private function __construct(string $value)
  {
    $this->value = $value;
  }
  //#endregion

  //#region Factory Methods
  /**
   * Method generate
   * @static
   *
   * Generates a new random challenge token.
   *
   * @access public
   * @since 1.0.0
   *
   * @return self
   */
  public static function generate(): self
  {
    // Generate a URL-safe random token (32 bytes = 64 hex chars)
    $randomBytes = random_bytes(32);
    $token = bin2hex($randomBytes);

    return new self($token);
  }

  /**
   * Method fromString
   * @static
   *
   * Creates from an existing token string.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $token The token string.
   *
   * @return self
   */
  public static function fromString(string $token): self
  {
    return new self($token);
  }
  //#endregion

  //#region Methods
  /**
   * Method equals
   *
   * Checks equality with another token.
   *
   * @access public
   * @since 1.0.0
   *
   * @param self $other The other token.
   *
   * @return bool
   */
  public function equals(self $other): bool
  {
    return hash_equals($this->value, $other->value);
  }

  /**
   * Method __toString
   *
   * Returns the token as string.
   *
   * @access public
   * @since 1.0.0
   *
   * @return string
   */
  public function __toString(): string
  {
    return $this->value;
  }
  //#endregion
}
