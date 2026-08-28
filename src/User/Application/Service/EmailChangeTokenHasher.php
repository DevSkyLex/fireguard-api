<?php

declare(strict_types=1);

namespace User\Application\Service;

use function bin2hex;
use function hash;
use function random_bytes;

/**
 * Service EmailChangeTokenHasher.
 *
 * Single source of truth for email change confirmation token
 * generation and hashing, shared by the request use case (issues the
 * token) and the confirm lookup (hashes what the caller presents), so
 * the algorithm is defined in exactly one place. Mirrors the
 * organization invitation token scheme: 32 bytes of CSPRNG, stored as
 * a SHA-256 hex digest only.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class EmailChangeTokenHasher
{
  // #region Methods
  /**
   * Method generate.
   *
   * Generates a cryptographically secure raw confirmation token.
   *
   * @since 1.0.0
   *
   * @return string the raw confirmation token (64 hex characters)
   */
  public function generate(): string
  {
    return bin2hex(random_bytes(32));
  }

  /**
   * Method hash.
   *
   * Computes the deterministic hash stored for token lookup.
   *
   * @since 1.0.0
   *
   * @param string $token the raw token
   *
   * @return string the token hash (SHA-256 hex digest)
   */
  public function hash(string $token): string
  {
    return hash('sha256', $token);
  }
  // #endregion
}
