<?php

declare(strict_types=1);

namespace Auth\Application\Service;

final readonly class SecurityUserCacheKeys
{
  public static function user(string $userId): string
  {
    return 'auth.security_user.' . $userId;
  }
}
