<?php

declare(strict_types=1);

namespace OAuth\Domain\ValueObject;

use Shared\Domain\ValueObject\HashedSecret;

use function bin2hex;
use function random_bytes;
use function ceil;
use function max;

/**
 * ValueObject ClientSecret
 * @final
 *
 * Represents a hashed OAuth client secret.
 * The plain secret is never stored, only the hash.
 *
 * @category ValueObject
 * @package OAuth\Domain\ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ClientSecret extends HashedSecret
{
  //#region Methods
  /**
   * Method generateRandomPlain
   * @static
   *
   * Generates a random plain secret.
   *
   * @access public
   * @since 1.0.0
   *
   * @param int $length The length of the secret (default: 32).
   *
   * @return string The random plain secret.
   */
  public static function generateRandomPlain(int $length = 32): string
  {
    return bin2hex(random_bytes(max(1, (int) ceil($length / 2))));
  }
  //#endregion
}
