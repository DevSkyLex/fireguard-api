<?php

declare(strict_types=1);

namespace Otp\Infrastructure\Adapter\Crypto;

use Otp\Application\Port\Outbound\Internal\Totp\RecoveryCodeGeneratorPort;

use function bin2hex;
use function implode;
use function random_bytes;
use function str_split;

/**
 * Class RecoveryCodeGeneratorAdapter
 *
 * Uses the operating system CSPRNG for ten independent 128-bit proofs.
 *
 * @category Adapter
 */
final readonly class RecoveryCodeGeneratorAdapter implements RecoveryCodeGeneratorPort
{
  // #region Methods
  /**
   * Method generate
   *
   * @access public
   *
   * @return list<string> ten plaintext codes, owned only by the current response
   */
  public function generate(): array
  {
    $codes = [];
    for ($index = 0; $index < 10; ++$index) {
      $codes[] = implode('-', str_split(bin2hex(random_bytes(16)), 4));
    }

    return $codes;
  }
  // #endregion
}
