<?php

declare(strict_types=1);

namespace Shared\Application\Port\Outbound;

use Shared\Domain\ValueObject\HashedSecret;

/**
 * Port HashingPort
 *
 * Provides hashing and verification
 * capabilities for secrets.
 *
 * @category Outbound Port
 * @package Shared\Application\Port\Outbound
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface HashingPort
{
  //#region Methods
  /**
   * Method hash
   *
   * Hash the provided value using a
   * secure algorithm and wrap it in a
   * HashedSecret value object.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $value The value to hash.
   *
   * @return HashedSecret The hashed value.
   */
  public function hash(string $value): HashedSecret;

  /**
   * Method verify
   *
   * Verifies that a plain value matches a
   * previously hashed value.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $value The plain value to verify.
   * @param HashedSecret $hashed The hashed value to verify.
   *
   * @return bool True if the values match, false otherwise.
   */
  public function verify(string $value, HashedSecret $hashed): bool;
  //#endregion
}
