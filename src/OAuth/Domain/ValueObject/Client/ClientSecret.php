<?php

declare(strict_types=1);

namespace OAuth\Domain\ValueObject\Client;

use Shared\Domain\ValueObject\HashedSecret;

use function bin2hex;
use function ceil;
use function max;
use function random_bytes;

/**
 * ValueObject ClientSecret.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ClientSecret extends HashedSecret
{
  // #region Methods
  /**
   * Method generateRandomPlain.
   *
   * @static
   *
   * Generates a random plain secret.
   *
   * @since 1.0.0
   *
   * @param int $length the length of the secret (default: 32)
   *
   * @return string the random plain secret
   */
  public static function generateRandomPlain(int $length = 32): string
  {
    return bin2hex(random_bytes(max(1, (int) ceil($length / 2))));
  }
  // #endregion
}
