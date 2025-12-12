<?php

declare(strict_types=1);

namespace Authorization\Application\Service;

use Authorization\Application\Port\Inbound\AuthorizationPort;
use Authorization\Application\Port\Outbound\RoleAssignmentRepositoryPort;
use Authorization\Domain\Model\{
  Permission,
  Role,
};
use Authorization\Domain\ValueObject\{
  PermissionName,
  SubjectType,
};

use function array_map;
use function in_array;

/**
 * Service AuthorizationService
 * @final
 *
 * Core service for authorization checks.
 * Provides permission and role checking for users.
 *
 * @category Service
 * @package Authorization\Application\Service
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AuthorizationService implements AuthorizationPort
{
  //#region Constructor
  /**
   * Constructor
   * 
   * Initialize a new instance of the 
   * AuthorizationService class.
   * 
   * @access public
   * @since 1.0.0
   *
   * @param RoleAssignmentRepositoryPort $roleAssignmentRepository The role assignment repository.
   */
  public function __construct(
    private readonly RoleAssignmentRepositoryPort $roleAssignmentRepository,
  ) {
  }
  //#endregion

  //#region Methods
  /**
   * Method hasPermission
   * {@inheritDoc}
   * 
   * Check if a user has a specific permission.
   * 
   * @access public
   * @since 1.0.0
   * 
   * @param string $userId The user ID.
   * @param string $permission The permission name.
   * 
   * @return bool True if the user has the permission, false otherwise.
   */
  public function hasPermission(string $userId, string $permission): bool
  {
    $requiredPermission = new PermissionName(value: $permission);

    // Get user's roles
    $userRoles = $this->roleAssignmentRepository->findRolesForSubject(
      subjectType: SubjectType::USER,
      subjectId: $userId
    );

    // Check permissions
    foreach ($userRoles as $role) {
      foreach ($role->permissions() as $rolePermission) {
        if ($rolePermission->matches(required: $requiredPermission)) {
          return true;
        }
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
   * @access public
   * @since 1.0.0
   * 
   * @param string $userId The user ID.
   * @param string $roleName The role name.
   * 
   * @return bool True if the user has the role, false otherwise.
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
   * @access public
   * @since 1.0.0
   * 
   * @param string $userId The user ID.
   * @param array<string> $roleNames The role names.
   * 
   * @return bool True if the user has any of the roles, false otherwise.
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
   * @access public
   * @since 1.0.0
   * 
   * @param string $userId The user ID.
   * @param array<string> $roleNames The role names.
   * 
   * @return bool True if the user has all roles, false otherwise.
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
   * Method getUserRoles
   * 
   * Gets all roles assigned to a user.
   * 
   * @access public
   * @since 1.0.0
   * 
   * @param string $userId The user ID.
   *
   * @return array<Role> The user's roles.
   */
  public function getUserRoles(string $userId): array
  {
    return $this->roleAssignmentRepository->findRolesForSubject(
      subjectType: SubjectType::USER,
      subjectId: $userId
    );
  }

  /**
   * Method getUserPermissions
   * 
   * Gets all permissions for a user 
   * (from all their roles).
   * 
   * @access public
   * @since 1.0.0
   * 
   * @param string $userId The user ID.
   *
   * @return array<Permission> The user's permissions.
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
   * @access public
   * @since 1.0.0
   * 
   * @param string $userId The user ID.
   *
   * @return array<string> The user's role names.
   */
  public function getUserRoleNames(string $userId): array
  {
    $roles = $this->getUserRoles(userId: $userId);
    return array_map(fn(Role $role) => $role->name()->value, $roles);
  }

  /**
   * Method getUserPermissionNames
   * {@inheritDoc}
   * 
   * Gets all permission names for a user.
   * 
   * @access public
   * @since 1.0.0
   * 
   * @param string $userId The user ID.
   *
   * @return array<string> The user's permission names.
   */
  public function getUserPermissionNames(string $userId): array
  {
    $permissions = $this->getUserPermissions(userId: $userId);
    return array_map(fn(Permission $p) => $p->name()->value, $permissions);
  }
  //#endregion
}
