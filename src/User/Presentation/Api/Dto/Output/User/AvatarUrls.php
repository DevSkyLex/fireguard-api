<?php

declare(strict_types=1);

namespace User\Presentation\Api\Dto\Output\User;

use User\Infrastructure\Image\AvatarResizer;

use function preg_match;
use function sprintf;

/**
 * Helper AvatarUrls.
 *
 * Derives the per-size avatar URLs from the canonical avatar URL
 * stored on the user (e.g. ".../avatar/256.webp").
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AvatarUrls
{
  // #region Methods
  /**
   * Method fromAvatarUrl.
   *
   * Builds the map of size (px) to URL from the canonical avatar URL.
   * Returns null when no avatar is set or when the URL does not point
   * to the internal avatar endpoint (e.g. an external picture URL).
   *
   * @since 1.0.0
   *
   * @param string|null $avatarUrl the canonical avatar URL
   *
   * @return array<int, string>|null map of size (px) to URL
   */
  public static function fromAvatarUrl(?string $avatarUrl): ?array
  {
    if (null === $avatarUrl) {
      return null;
    }

    if (1 !== preg_match('#^(.+/avatar)(?:/\d+\.webp)?(\?.*)?$#', $avatarUrl, $matches)) {
      return null;
    }

    // Preserve any cache-busting query (e.g. "?v=1700000000") on every variant
    // so a fresh upload invalidates the browser cache for all sizes at once.
    $query = $matches[2] ?? '';

    $urls = [];
    foreach (AvatarResizer::SIZES as $size) {
      $urls[$size] = sprintf('%s/%d.webp%s', $matches[1], $size, $query);
    }

    return $urls;
  }
  // #endregion
}
