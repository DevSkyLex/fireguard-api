<?php

declare(strict_types=1);

namespace OAuth\Application\Port\Inbound;

use OAuth\Application\Contract\Client\ClientSecurityProof;

/**
 * Interface ClientSecurityProofPort
 *
 * Rechecks current stored client credentials and grant configuration before decrypting any protocol response replay.
 *
 * @category Port
 */
interface ClientSecurityProofPort
{
  // #region Methods
  /**
   * Method verify
   *
   * Unknown, deleted, disabled, secret-mismatched and disallowed-grant clients receive no proof.
   *
   * @access public
   *
   * @param string $clientId OAuth client identity
   * @param string $clientSecret plaintext current client credential; never retained
   * @param string $grantType exact requested protocol grant
   *
   * @return ?ClientSecurityProof fresh verified client facts
   */
  public function verify(string $clientId, string $clientSecret, string $grantType): ?ClientSecurityProof;
  // #endregion
}
