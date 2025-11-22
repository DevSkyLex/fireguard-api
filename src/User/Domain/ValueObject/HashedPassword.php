<?php

declare(strict_types=1);

namespace User\Domain\ValueObject;

use Shared\Domain\ValueObject\HashedSecret;

/**
 * ValueObject HashedPassword
 * @final
 *
 * Represents a hashed user password.
 * 
 * Extends HashedSecret to ensure passwords are always 
 * stored hashed.
 *
 * @category ValueObject
 * @package User\Domain\ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class HashedPassword extends HashedSecret
{
  //#region Methods
  /**
   * Method fromPlain
   * @static
   *
   * Creates a HashedPassword from a plain 
   * text password.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $plain The plain text password.
   *
   * @return self The hashed password.
   */
  public static function fromPlain(string $plain): self
  {
    $hashed = password_hash(
      password: $plain, 
      algo: PASSWORD_BCRYPT
    );
    
    return new self(value: $hashed);
  }

  /**
   * Method verify
   *
   * Verifies a plain text password against this hashed password.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $plain The plain text password to verify.
   *
   * @return bool True if the password matches, false otherwise.
   */
  public function verify(string $plain): bool
  {
    return password_verify(
      password: $plain, 
      hash: $this->value
    );
  }
  //#endregion
}
