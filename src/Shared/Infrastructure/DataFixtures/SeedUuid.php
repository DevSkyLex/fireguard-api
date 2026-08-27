<?php

declare(strict_types=1);

namespace Shared\Infrastructure\DataFixtures;

use function dechex;
use function hash;
use function hexdec;
use function sprintf;
use function substr;

/**
 * DataFixtures SeedUuid.
 *
 * Derives a stable, RFC 4122-shaped identifier from a human-readable seed
 * string, so fixture rows get realistic-looking hex instead of the
 * hand-counted `33333333-3333-4333-8333-333333333340` style that makes a
 * seeded record instantly recognisable as fake at a glance.
 *
 * The same seed always yields the same identifier — this is a hash, not a
 * random generator — so a fixture's row keeps the same primary key across
 * every `make seed-fixtures` run without needing to hand-author or track a
 * literal UUID for it. Cross-module identifier agreement (e.g. a member id
 * `OrganizationFixtures` mints that `MessagingFixtures` also needs) works by
 * both call sites hashing the same seed string, not by copying a literal.
 *
 * Not a replacement for every literal UUID in the fixture layer: a handful of
 * anchor identifiers (the admin user, the seed organization, a few equipment
 * rows) are hardcoded literals reused verbatim by unrelated modules and
 * dozens of unit tests as generic placeholder ids. Those stay literal on
 * purpose — hashing them would silently change a value other test suites
 * assert against.
 *
 * @category DataFixtures
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class SeedUuid
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Private to prevent instantiation: this helper only exposes statics.
   *
   * @since 1.0.0
   *
   * @codeCoverageIgnore Never executed: the constructor exists only to
   * forbid instantiation of this static catalogue.
   */
  private function __construct()
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method from.
   *
   * Hashes `$seed` into a UUID shaped like a real (version 4, variant
   * RFC 4122) identifier. The hash is SHA-256, not a cryptographic UUID
   * generator — collisions are astronomically unlikely for the handful of
   * distinct seeds a fixture file ever hashes, but this is not meant for
   * anything security-sensitive.
   *
   * @since 1.0.0
   *
   * @param string $seed the human-readable seed, unique per intended row
   *
   * @return string the derived UUID, lowercase, dash-separated
   */
  public static function from(string $seed): string
  {
    $hex = substr(hash('sha256', $seed), 0, 32);
    $hex[12] = '4';
    $hex[16] = dechex((hexdec($hex[16]) & 0x3) | 0x8);

    return sprintf(
      '%s-%s-%s-%s-%s',
      substr($hex, 0, 8),
      substr($hex, 8, 4),
      substr($hex, 12, 4),
      substr($hex, 16, 4),
      substr($hex, 20, 12),
    );
  }
  // #endregion
}
