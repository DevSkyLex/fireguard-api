<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\Service;

use Organization\Application\Service\OrganizationCacheKeys;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test OrganizationCacheKeys.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(OrganizationCacheKeys::class)]
final class OrganizationCacheKeysTest extends TestCase
{
  #[Test]
  public function testPermissionsKeyIsNamespacedByOrganizationAndUser(): void
  {
    self::assertSame(
      'organization.permissions.org-1.user-1',
      OrganizationCacheKeys::permissions('org-1', 'user-1'),
    );
  }

  #[Test]
  public function testCurrentMemberProfileKeyIsNamespacedByOrganizationAndUser(): void
  {
    self::assertSame(
      'organization.member_profile.org-1.user-1',
      OrganizationCacheKeys::currentMemberProfile('org-1', 'user-1'),
    );
  }

  #[Test]
  public function testKeysForTheSamePairDoNotCollideAcrossConcerns(): void
  {
    self::assertNotSame(
      OrganizationCacheKeys::permissions('org-1', 'user-1'),
      OrganizationCacheKeys::currentMemberProfile('org-1', 'user-1'),
    );
  }

  #[Test]
  public function testKeysVaryWithTheUserIdentifier(): void
  {
    self::assertNotSame(
      OrganizationCacheKeys::permissions('org-1', 'user-1'),
      OrganizationCacheKeys::permissions('org-1', 'user-2'),
    );
  }
}
