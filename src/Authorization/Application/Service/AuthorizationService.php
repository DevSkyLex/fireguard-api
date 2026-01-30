<?php

declare(strict_types=1);

namespace Authorization\Application\Service;

use Authorization\Application\Port\Inbound\AuthorizationPort;
use Authorization\Application\Port\Outbound\RoleAssignmentRepositoryPort;
use Authorization\Domain\Model\Permission\Permission;
use Authorization\Domain\Model\Role\Role;
use Authorization\Domain\ValueObject\{
  PermissionName,
  SubjectType,
};
use Shared\Application\Port\Outbound\CachePort;
use Throwable;

use function array_map;
use function in_array;
use function is_array;

/**
 * Service AuthorizationService.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AuthorizationService implements AuthorizationPort
{
  private const string PERMISSIONS_CACHE_PREFIX = 'authz.permissions.';

  // #region Constructor
  /**
   * Constructor.
   *
   * Initialize a new instance of the
   * AuthorizationService class.
   *
   * @since 1.0.0
   *
   * @param RoleAssignmentRepositoryPort $roleAssignmentRepository the role assignment repository
   * @param CachePort $cache the cache port
   * @param int $permissionCacheTtl permission cache TTL in seconds
   */
  public function __construct(
    private readonly RoleAssignmentRepositoryPort $roleAssignmentRepository,
    private readonly CachePort $cache,
    private readonly int $permissionCacheTtl = 60,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method hasPermission
   * {@inheritDoc}
   *
   * Check if a user has a specific permission.
   *
   * @since 1.0.0
   *
   * @param string $userId the user ID
   * @param string $permission the permission name
   *
   * @return bool true if the user has the permission, false otherwise
   */
  public function hasPermission(string $userId, string $permission): bool
  {
    $requiredPermission = new PermissionName(value: $permission);

    foreach ($this->getCachedPermissionNames(userId: $userId) as $permissionName) {
      if ('' === $permissionName) {
        continue;
      }

      if (new PermissionName(value: $permissionName)->matches(required: $requiredPermission)) {
        return true;
      }
    }

    return false;
  }

  /**
   * Method hasRole
   * {@inheritDoc}
   *
   * Check if a user has a specific role.
   *
   * @since 1.0.0
   *
   * @param string $userId the user ID
   * @param string $roleName the role name
   *
   * @return bool true if the user has the role, false otherwise
   */
  public function hasRole(string $userId, string $roleName): bool
  {
    $roleNames = $this->getUserRoleNames(userId: $userId);

    return in_array($roleName, $roleNames, true);
  }

  /**
   * Method hasAnyRole
   * {@inheritDoc}
   *
   * Check if a user has any of the specified roles.
   *
   * @since 1.0.0
   *
   * @param string $userId the user ID
   * @param array<string> $roleNames the role names
   *
   * @return bool true if the user has any of the roles, false otherwise
   */
  public function hasAnyRole(string $userId, array $roleNames): bool
  {
    $userRoleNames = $this->getUserRoleNames(userId: $userId);

    foreach ($roleNames as $roleName) {
      if (in_array($roleName, $userRoleNames, true)) {
        return true;
      }
    }

    return false;
  }

  /**
   * Method hasAllRoles
   * {@inheritDoc}
   *
   * Check if a user has all specified roles.
   *
   * @since 1.0.0
   *
   * @param string $userId the user ID
   * @param array<string> $roleNames the role names
   *
   * @return bool true if the user has all roles, false otherwise
   */
  public function hasAllRoles(string $userId, array $roleNames): bool
  {
    $userRoleNames = $this->getUserRoleNames(userId: $userId);

    foreach ($roleNames as $roleName) {
      if (!in_array($roleName, $userRoleNames, true)) {
        return false;
      }
    }

    return true;
  }

  /**
   * Method getUserRoles.
   *
   * Gets all roles assigned to a user.
   *
   * @since 1.0.0
   *
   * @param string $userId the user ID
   *
   * @return array<Role> the user's roles
   */
  public function getUserRoles(string $userId): array
  {
    return $this->roleAssignmentRepository->findRolesForSubject(
      subjectType: SubjectType::USER,
      subjectId: $userId,
    );
  }

  /**
   * Method getUserPermissions.
   *
   * Gets all permissions for a user
   * (from all their roles).
   *
   * @since 1.0.0
   *
   * @param string $userId the user ID
   *
   * @return array<Permission> the user's permissions
   */
  public function getUserPermissions(string $userId): array
  {
    $roles = $this->getUserRoles(userId: $userId);

    $permissions = [];
    $seen = [];

    foreach ($roles as $role) {
      foreach ($role->permissions() as $permission) {
        $key = $permission->id()->value;
        if (!isset($seen[$key])) {
          $permissions[] = $permission;
          $seen[$key] = true;
        }
      }
    }

    return $permissions;
  }

  /**
   * Method getUserRoleNames
   * {@inheritDoc}
   *
   * Gets all role names for a user.
   *
   * @since 1.0.0
   *
   * @param string $userId the user ID
   *
   * @return array<string> the user's role names
   */
  public function getUserRoleNames(string $userId): array
  {
    $roles = $this->getUserRoles(userId: $userId);

    return array_map(fn (Role $role) => $role->name()->value, $roles);
  }

  /**
   * Method getUserPermissionNames
   * {@inheritDoc}
   *
   * Gets all permission names for a user.
   *
   * @since 1.0.0
   *
   * @param string $userId the user ID
   *
   * @return array<string> the user's permission names
   */
  public function getUserPermissionNames(string $userId): array
  {
    return $this->getCachedPermissionNames(userId: $userId);
  }

  /**
   * @return list<string>
   */
  private function getCachedPermissionNames(string $userId): array
  {
    $cacheKey = self::PERMISSIONS_CACHE_PREFIX . $userId;

    try {
      $cached = $this->cache->get(key: $cacheKey);
      if (is_array($cached)) {
        /** @var list<string> $cached */
        return $cached;
      }
    } catch (Throwable) {
      // Cache failures should not block authorization checks
    }

    $permissions = $this->buildPermissionNames(userId: $userId);

    try {
      $this->cache->set(
        key: $cacheKey,
        value: $permissions,
        ttl: $this->permissionCacheTtl,
      );
    } catch (Throwable) {
      // Ignore cache write failures
    }

    return $permissions;
  }

  /**
   * @return list<string>
   */
  private function buildPermissionNames(string $userId): array
  {
    $userRoles = $this->roleAssignmentRepository->findRolesForSubject(
      subjectType: SubjectType::USER,
      subjectId: $userId,
    );

    $names = [];
    $seen = [];

    foreach ($userRoles as $role) {
      foreach ($role->permissions() as $rolePermission) {
        $name = $rolePermission->name()->value;
        if (!isset($seen[$name])) {
          $names[] = $name;
          $seen[$name] = true;
        }
      }
    }

    return $names;
  }
  // #endregion
}
