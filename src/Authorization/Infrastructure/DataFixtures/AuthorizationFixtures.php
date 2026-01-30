<?php

declare(strict_types=1);

namespace Authorization\Infrastructure\DataFixtures;

use Authorization\Domain\ValueObject\SubjectType;
use Authorization\Infrastructure\Catalog\{PermissionCatalog, RoleCatalog};
use Authorization\Infrastructure\Persistence\Doctrine\Record\{PermissionRecord, RoleAssignmentRecord, RoleRecord};
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\{Fixture, FixtureGroupInterface};
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Uid\Uuid;

/**
 * Fixtures AuthorizationFixtures.
 *
 * Creates default roles and permissions for the RBAC system.
 *
 * @category DataFixtures
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
class AuthorizationFixtures extends Fixture implements FixtureGroupInterface
{
  // #region Constants
  /**
   * Constant ROLE_SUPER_ADMIN.
   *
   * Reference key for super admin
   * role fixture
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string ROLE_SUPER_ADMIN = 'role-super-admin';

  /**
   * Constant ROLE_ADMIN.
   *
   * Reference key for admin role fixture
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string ROLE_ADMIN = 'role-admin';

  /**
   * Constant ROLE_USER.
   *
   * Reference key for user role fixture
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string ROLE_USER = 'role-user';
  // #endregion

  // #region Methods
  /**
   * Method getGroups
   * {@inheritDoc}
   *
   * Returns the groups that this fixture
   * belongs to.
   *
   * @since 1.0.0
   *
   * @return array<string> the groups
   */
  public static function getGroups(): array
  {
    return ['authorization'];
  }

  /**
   * Method load
   * {@inheritDoc}
   *
   * Loads the fixtures.
   *
   * @since 1.0.0
   *
   * @param ObjectManager $manager the object manager
   *
   * @return void none
   */
  public function load(ObjectManager $manager): void
  {
    // Create permissions (flush to get IDs for ManyToMany relation)
    $permissionRecords = $this->createPermissions(manager: $manager);
    $manager->flush();

    // Create roles with permissions
    $this->createRoles(
      manager: $manager,
      permissions: $permissionRecords,
    );

    $manager->flush();

    // Assign Super Admin role to Dev Client (for E2E tests)
    /** @var RoleRecord $superAdminRole */
    $superAdminRole = $this->getReference(self::ROLE_SUPER_ADMIN, RoleRecord::class);

    $assignment = new RoleAssignmentRecord();
    $assignment->id = Uuid::v7()->toRfc4122();
    $assignment->role = $superAdminRole;
    $assignment->roleId = $superAdminRole->id;
    $assignment->subjectType = SubjectType::USER->value;
    $assignment->subjectId = 'a7b8c9d0-e1f2-4456-8123-789012345678'; // Dev Client ID
    $assignment->assignedAt = new DateTimeImmutable();

    $manager->persist($assignment);
    $manager->flush();
  }

  /**
   * Method createPermissions.
   *
   * Creates default permissions.
   *
   * @since 1.0.0
   *
   * @param ObjectManager $manager the object manager
   *
   * @return array<string, PermissionRecord> the permissions
   */
  private function createPermissions(ObjectManager $manager): array
  {
    $permissionDefinitions = PermissionCatalog::definitions();

    $permissionRecords = [];
    foreach ($permissionDefinitions as $def) {
      $record = new PermissionRecord();
      $record->id = Uuid::v7()->toRfc4122();
      $record->name = $def['name'];
      $record->description = $def['description'];
      $record->createdAt = new DateTimeImmutable();

      $manager->persist($record);
      $permissionRecords[$def['name']] = $record;
    }

    return $permissionRecords;
  }

  /**
   * Method createRoles.
   *
   * Creates default roles.
   *
   * @since 1.0.0
   *
   * @param ObjectManager $manager the object manager
   * @param array<string, PermissionRecord> $permissions the permissions
   *
   * @return void none
   */
  private function createRoles(ObjectManager $manager, array $permissions): void
  {
    // Super Admin role - full access
    $superAdminRecord = new RoleRecord();
    $superAdminRecord->id = Uuid::v7()->toRfc4122();
    $superAdminRecord->name = 'super_admin';
    $superAdminRecord->description = 'Full system access with all permissions';
    $superAdminRecord->isSystem = true;
    $superAdminRecord->createdAt = new DateTimeImmutable();
    $superAdminRecord->permissions->add($permissions['*.*']);
    $manager->persist($superAdminRecord);
    $this->addReference(self::ROLE_SUPER_ADMIN, $superAdminRecord);

    // Admin role - administrative access
    $adminRecord = new RoleRecord();
    $adminRecord->id = Uuid::v7()->toRfc4122();
    $adminRecord->name = 'admin';
    $adminRecord->description = 'Administrative access for user and client management';
    $adminRecord->isSystem = true;
    $adminRecord->createdAt = new DateTimeImmutable();
    foreach (RoleCatalog::adminPermissionNames() as $permissionName) {
      if (isset($permissions[$permissionName])) {
        $adminRecord->permissions->add($permissions[$permissionName]);
      }
    }
    $manager->persist($adminRecord);
    $this->addReference(self::ROLE_ADMIN, $adminRecord);

    // User role - standard user access
    $userRecord = new RoleRecord();
    $userRecord->id = Uuid::v7()->toRfc4122();
    $userRecord->name = 'user';
    $userRecord->description = 'Standard user access with profile and session management';
    $userRecord->isSystem = true;
    $userRecord->createdAt = new DateTimeImmutable();
    foreach (RoleCatalog::userPermissionNames() as $permissionName) {
      if (isset($permissions[$permissionName])) {
        $userRecord->permissions->add($permissions[$permissionName]);
      }
    }
    $manager->persist($userRecord);
    $this->addReference(self::ROLE_USER, $userRecord);
  }
  // #endregion
}
