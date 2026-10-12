<?php

declare(strict_types=1);

namespace Otp\Application\Port\Outbound\Internal\Totp;

/**
 * Interface RecoveryCodeGeneratorPort
 *
 * Generates independent cryptographically random 128-bit recovery proofs for immediate delivery only.
 *
 * @category Port
 */
interface RecoveryCodeGeneratorPort
{
  // #region Methods
  /**
   * Method generate
   *
   * @access public
   *
   * @return list<string> ten distinct formatted recovery codes
   */
  public function generate(): array;
  // #endregion
}
