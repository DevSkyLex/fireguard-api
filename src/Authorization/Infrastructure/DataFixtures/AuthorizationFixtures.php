<?php

declare(strict_types=1);

namespace Authorization\Infrastructure\DataFixtures;

use Authorization\Domain\ValueObject\SubjectType;
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
    $permissionDefinitions = [
      // User management
      ['name' => 'users.create', 'description' => 'Create new users'],
      ['name' => 'users.read', 'description' => 'View user information'],
      ['name' => 'users.update', 'description' => 'Modify user data'],
      ['name' => 'users.delete', 'description' => 'Delete users'],
      ['name' => 'users.*', 'description' => 'All user operations'],

      // Client (OAuth2) management
      ['name' => 'clients.create', 'description' => 'Create OAuth2 clients'],
      ['name' => 'clients.read', 'description' => 'View OAuth2 clients'],
      ['name' => 'clients.update', 'description' => 'Modify OAuth2 clients'],
      ['name' => 'clients.delete', 'description' => 'Delete OAuth2 clients'],
      ['name' => 'clients.*', 'description' => 'All client operations'],

      // Role management
      ['name' => 'roles.create', 'description' => 'Create roles'],
      ['name' => 'roles.read', 'description' => 'View roles'],
      ['name' => 'roles.update', 'description' => 'Modify roles'],
      ['name' => 'roles.delete', 'description' => 'Delete roles'],
      ['name' => 'roles.assign', 'description' => 'Assign roles to users'],
      ['name' => 'roles.*', 'description' => 'All role operations'],

      // Permission management
      ['name' => 'permissions.read', 'description' => 'View permissions'],
      ['name' => 'permissions.manage', 'description' => 'Manage permissions'],

      // Profile (self-service)
      ['name' => 'profile.read', 'description' => 'View own profile'],
      ['name' => 'profile.update', 'description' => 'Update own profile'],

      // Session management
      ['name' => 'sessions.read', 'description' => 'View own sessions'],
      ['name' => 'sessions.revoke', 'description' => 'Revoke own sessions'],

      // Tenant management
      ['name' => 'tenants.create', 'description' => 'Create tenants'],
      ['name' => 'tenants.read', 'description' => 'View tenants'],
      ['name' => 'tenants.update', 'description' => 'Modify tenants'],
      ['name' => 'tenants.delete', 'description' => 'Delete tenants'],
      ['name' => 'tenants.*', 'description' => 'All tenant operations'],

      // Super admin wildcard
      ['name' => '*.*', 'description' => 'Full system access (super admin)'],
    ];

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
    $adminRecord->permissions->add($permissions['users.*']);
    $adminRecord->permissions->add($permissions['clients.*']);
    $adminRecord->permissions->add($permissions['roles.read']);
    $manager->persist($adminRecord);
    $this->addReference(self::ROLE_ADMIN, $adminRecord);

    // User role - standard user access
    $userRecord = new RoleRecord();
    $userRecord->id = Uuid::v7()->toRfc4122();
    $userRecord->name = 'user';
    $userRecord->description = 'Standard user access with profile and session management';
    $userRecord->isSystem = true;
    $userRecord->createdAt = new DateTimeImmutable();
    $userRecord->permissions->add($permissions['profile.read']);
    $userRecord->permissions->add($permissions['profile.update']);
    $userRecord->permissions->add($permissions['sessions.read']);
    $userRecord->permissions->add($permissions['sessions.revoke']);
    $manager->persist($userRecord);
    $this->addReference(self::ROLE_USER, $userRecord);
  }
  // #endregion
}
