<?php

declare(strict_types=1);

namespace User\Application\Contract\User;

use function sprintf;

/** Canonical public avatars and immutable attempt-owned storage keys.
 * @category Contract
 */
final class AvatarPaths
{
  public static function variant(string $userId, ?string $generation, int $size): string
  {
    return null === $generation
      ? sprintf('avatars/%s/%d.webp', $userId, $size)
      : sprintf('avatars/%s/%s/%d.webp', $userId, $generation, $size);
  }

  /**
   * @return list<string>
   */
  public static function variants(string $userId, ?string $generation): array
  {
    $paths = [];
    foreach (AvatarVariants::SIZES as $size) {
      $paths[] = self::variant($userId, $generation, $size);
    }

    return $paths;
  }
}
