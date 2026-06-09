<?php

declare(strict_types=1);

namespace Organization\Application\Service;

final readonly class OrganizationCacheKeys
{
  public static function permissions(string $organizationId, string $userId): string
  {
    return 'organization.permissions.' . $organizationId . '.' . $userId;
  }

  public static function currentMemberProfile(string $organizationId, string $userId): string
  {
    return 'organization.member_profile.' . $organizationId . '.' . $userId;
  }
}
