<?php

declare(strict_types=1);

namespace Authorization\Infrastructure\DataFixtures;

use Authorization\Application\Port\Outbound\{PermissionCatalogPort, RoleCatalogPort};
use Authorization\Infrastructure\Persistence\Doctrine\Record\{PermissionRecord, RoleRecord};
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

  // #region Constructor
  /**
   * Method __construct
   *
   * Seeds the composed owner vocabulary and bootstrap policy.
   *
   * @access public
   *
   * @param PermissionCatalogPort $permissionCatalog registered permission vocabulary
   * @param RoleCatalogPort $roleCatalog registered bootstrap policy
   *
   * @return void
   */
  public function __construct(
    private readonly PermissionCatalogPort $permissionCatalog,
    private readonly RoleCatalogPort $roleCatalog,
  ) {
  }
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
    return ['authorization', 'auth-seed'];
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
    $permissionDefinitions = $this->permissionCatalog->definitions();

    $permissionRecords = [];
    foreach ($permissionDefinitions as $def) {
      $record = new PermissionRecord();
      $record->id = Uuid::v7()->toRfc4122();
      $record->name = $def->name;
      $record->description = $def->description;
      $record->createdAt = new DateTimeImmutable();

      $manager->persist($record);
      $permissionRecords[$def->name] = $record;
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
    foreach ($this->roleCatalog->definitions() as $definition) {
      $record = new RoleRecord();
      $record->id = Uuid::v7()->toRfc4122();
      $record->name = $definition->name;
      $record->description = $definition->description;
      $record->isSystem = $definition->isSystem;
      $record->createdAt = new DateTimeImmutable();
      foreach ($definition->permissionNames as $permissionName) {
        $record->permissions->add($permissions[$permissionName]);
      }
      $manager->persist($record);
      if (null !== $definition->reference) {
        $this->addReference($definition->reference, $record);
      }
    }
  }
  // #endregion
}
