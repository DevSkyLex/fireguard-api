<?php

declare(strict_types=1);

namespace Otp\Domain\ValueObject;

use function hash;
use function preg_match;
use function str_replace;
use function strtolower;

/**
 * Class RecoveryCode
 *
 * Canonicalizes a 128-bit recovery proof before hashing; plaintext is never retained in enrollment state.
 *
 * @category ValueObject
 */
final readonly class RecoveryCode
{
  // #region Methods
  /**
   * Method hash
   *
   * @access public
   *
   * @param string $proof formatted or compact hexadecimal recovery proof
   *
   * @return ?string the SHA-256 digest, or null for a malformed proof
   */
  public static function hash(string $proof): ?string
  {
    if (1 !== preg_match('/\A(?:[a-fA-F0-9]{32}|[a-fA-F0-9]{4}(?:-[a-fA-F0-9]{4}){7})\z/', $proof)) {
      return null;
    }

    return hash('sha256', strtolower(str_replace('-', '', $proof)));
  }
  // #endregion
}
