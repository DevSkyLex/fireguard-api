<?php

declare(strict_types=1);

namespace Shared\Application\Port\Outbound;

use Shared\Domain\ValueObject\HashedSecret;

/**
 * Port HashingPort.
 *
 * Provides hashing and verification
 * capabilities for secrets.
 *
 * @category Outbound Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface HashingPort
{
  // #region Methods
  /**
   * Method hash.
   *
   * Hash the provided value using a
   * secure algorithm and wrap it in a
   * HashedSecret value object.
   *
   * @since 1.0.0
   *
   * @param string $value the value to hash
   *
   * @return HashedSecret the hashed value
   */
  public function hash(string $value): HashedSecret;

  /**
   * Method verify.
   *
   * Verifies that a plain value matches a
   * previously hashed value.
   *
   * @since 1.0.0
   *
   * @param string       $value  the plain value to verify
   * @param HashedSecret $hashed the hashed value to verify
   *
   * @return bool true if the values match, false otherwise
   */
  public function verify(string $value, HashedSecret $hashed): bool;
  // #endregion
}
