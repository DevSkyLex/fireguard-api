<?php

declare(strict_types=1);

namespace Shared\Application\Port\Outbound\Idempotency;

/**
 * Interface IdempotencyFingerprintPort
 *
 * Produces purpose-separated HMAC digests with a dedicated non-encryption key.
 *
 * @category Outbound Port
 */
interface IdempotencyFingerprintPort
{
  // #region Methods
  /**
   * Method digest
   *
   * @access public
   *
   * @param string $purpose fixed protocol domain separator
   * @param mixed $value scalar/list/map canonical value; objects and invalid numbers are denied
   *
   * @return string lowercase SHA-256 HMAC
   */
  public function digest(string $purpose, mixed $value): string;
  // #endregion
}
