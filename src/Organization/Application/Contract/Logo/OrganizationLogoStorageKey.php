<?php

declare(strict_types=1);

namespace Organization\Application\Contract\Logo;

use function is_string;
use function parse_str;
use function parse_url;
use function preg_match;
use function sprintf;

use const PHP_URL_QUERY;

/**
 * Contract OrganizationLogoStorageKey.
 *
 * Resolves immutable upload keys while retaining historical canonical files.
 *
 * @category Contract
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationLogoStorageKey
{
  // #region Methods
  /**
   * Builds a key for the current persisted URL or a new upload generation.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   * @param ?string $logoUrl the persisted public logo URL
   *
   * @return string the corresponding storage key
   */
  public static function fromUrl(string $organizationId, ?string $logoUrl): string
  {
    $query = null === $logoUrl ? null : parse_url($logoUrl, PHP_URL_QUERY);
    $parameters = [];
    if (is_string($query)) {
      parse_str($query, $parameters);
    }
    $version = $parameters['v'] ?? null;
    if (is_string($version) && 1 === preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/D', $version)) {
      return self::forAttempt($organizationId, $version);
    }

    return sprintf('organization-logos/%s/logo.webp', $organizationId);
  }

  /**
   * Builds a storage key unique to the owning upload attempt.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   * @param string $attemptId the generated upload identity
   *
   * @return string the immutable file key
   */
  public static function forAttempt(string $organizationId, string $attemptId): string
  {
    return sprintf('organization-logos/%s/%s.webp', $organizationId, $attemptId);
  }
  // #endregion
}
