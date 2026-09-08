<?php

declare(strict_types=1);

namespace Calendar\Application\Service;

use function base64_encode;
use function hash;
use function random_bytes;
use function rtrim;
use function strtr;

/**
 * Service CalendarFeedTokenSecretFactory.
 *
 * Single source of truth for feed token secret generation and hashing,
 * mirroring `Organization\Application\Service\OrganizationInvitationTokenHasher`.
 * The secret is 32 bytes of CSPRNG output encoded base64url (43 characters,
 * URL-safe — it travels inside a path segment), and only its SHA-256 hash is
 * ever persisted.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CalendarFeedTokenSecretFactory
{
  // #region Methods
  /**
   * Method generate.
   *
   * Generates a cryptographically secure, URL-safe raw feed token secret.
   *
   * @since 1.0.0
   *
   * @return string the raw secret (base64url, 43 characters, no padding)
   */
  public function generate(): string
  {
    return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
  }

  /**
   * Method hash.
   *
   * Computes the deterministic hash stored for feed token lookup.
   *
   * @since 1.0.0
   *
   * @param string $secret the raw secret
   *
   * @return string the SHA-256 hash, hex encoded
   */
  public function hash(string $secret): string
  {
    return hash('sha256', $secret);
  }
  // #endregion
}
