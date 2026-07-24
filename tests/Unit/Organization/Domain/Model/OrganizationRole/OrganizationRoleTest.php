<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Domain\Model\OrganizationRole;

use DateTimeImmutable;
use Organization\Domain\Model\OrganizationRole\OrganizationRole;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationRoleId, OrganizationRoleName};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test OrganizationRole.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(OrganizationRole::class)]
final class OrganizationRoleTest extends TestCase
{
  private const string ROLE_ID = '11111111-1111-4111-8111-111111111111';

  private const string ORGANIZATION_ID = '22222222-2222-4222-8222-222222222222';

  #[Test]
  public function testCreateInitializesRole(): void
  {
    $role = OrganizationRole::create(
      OrganizationRoleId::fromString(self::ROLE_ID),
      OrganizationId::fromString(self::ORGANIZATION_ID),
      new OrganizationRoleName('site_manager'),
      ['organization.read'],
      false,
      'Site manager role',
    );

    self::assertSame(self::ROLE_ID, $role->id()->value);
    self::assertSame(self::ORGANIZATION_ID, $role->organizationId()->value);
    self::assertSame('site_manager', (string) $role->name());
    self::assertSame(['organization.read'], $role->permissions());
    self::assertFalse($role->isSystem());
    self::assertSame('Site manager role', $role->description());
  }

  #[Test]
  public function testCreateDefaultsAreEmpty(): void
  {
    $role = OrganizationRole::create(
      OrganizationRoleId::fromString(self::ROLE_ID),
      OrganizationId::fromString(self::ORGANIZATION_ID),
      new OrganizationRoleName('member'),
      [],
    );

    self::assertFalse($role->isSystem());
    self::assertSame('', $role->description());
    self::assertInstanceOf(DateTimeImmutable::class, $role->createdAt());
  }

  #[Test]
  public function testReconstitutePreservesState(): void
  {
    $createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    $role = OrganizationRole::reconstitute(
      OrganizationRoleId::fromString(self::ROLE_ID),
      OrganizationId::fromString(self::ORGANIZATION_ID),
      new OrganizationRoleName('admin'),
      ['organization.*'],
      true,
      $createdAt,
      'Administrator',
    );

    self::assertTrue($role->isSystem());
    self::assertSame($createdAt, $role->createdAt());
    self::assertSame(['organization.*'], $role->permissions());
  }

  #[Test]
  public function testUpdatePermissionsReplacesList(): void
  {
    $role = OrganizationRole::create(
      OrganizationRoleId::fromString(self::ROLE_ID),
      OrganizationId::fromString(self::ORGANIZATION_ID),
      new OrganizationRoleName('member'),
      ['organization.read'],
    );

    $role->updatePermissions(['organization.read', 'organization.members.read']);

    self::assertSame(['organization.read', 'organization.members.read'], $role->permissions());
  }

  #[Test]
  public function testUpdateDescriptionReplacesValue(): void
  {
    $role = OrganizationRole::create(
      OrganizationRoleId::fromString(self::ROLE_ID),
      OrganizationId::fromString(self::ORGANIZATION_ID),
      new OrganizationRoleName('member'),
      [],
    );

    $role->updateDescription('New description');

    self::assertSame('New description', $role->description());
  }
}
