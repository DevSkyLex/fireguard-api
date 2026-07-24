<?php

declare(strict_types=1);

namespace Authorization\Application\Service;

final readonly class AuthorizationCacheKeys
{
  public static function permissions(string $userId): string
  {
    return 'authz.permissions.' . $userId;
  }

  public static function roles(string $userId): string
  {
    return 'authz.roles.' . $userId;
  }
}
