<?php

declare(strict_types=1);

namespace Organization\Application\Service;

use function bin2hex;
use function hash;
use function random_bytes;

/**
 * Service OrganizationInvitationTokenHasher.
 *
 * Single source of truth for invitation token generation and hashing, shared by
 * the invite/resend notifier and the accept/preview lookups so the algorithm is
 * defined in exactly one place.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationInvitationTokenHasher
{
  // #region Methods
  /**
   * Method generate.
   *
   * Generates a cryptographically secure raw invitation token.
   *
   * @since 1.0.0
   *
   * @return string the raw invitation token
   */
  public function generate(): string
  {
    return bin2hex(random_bytes(32));
  }

  /**
   * Method hash.
   *
   * Computes the deterministic hash stored for invitation token lookup.
   *
   * @since 1.0.0
   *
   * @param string $token the raw token
   *
   * @return string the token hash
   */
  public function hash(string $token): string
  {
    return hash('sha256', $token);
  }
  // #endregion
}
