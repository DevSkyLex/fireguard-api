<?php

declare(strict_types=1);

namespace Authorization\Application\Port\Inbound;

/**
 * Port AuthorizationPort.
 *
 * Inbound port for authorization operations.
 * Defines the contract for checking permissions and roles.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface AuthorizationPort
{
    // #region Methods
    /**
     * Method hasPermission.
     *
     * Checks if a user has a specific permission.
     *
     * @since 1.0.0
     *
     * @param string $userId     the user ID
     * @param string $permission The permission to check (e.g., "users.create").
     *
     * @return bool true if the user has the permission
     */
    public function hasPermission(string $userId, string $permission): bool;

    /**
     * Method hasRole.
     *
     * Checks if a user has a specific role.
     *
     * @since 1.0.0
     *
     * @param string $userId   the user ID
     * @param string $roleName The role name to check (e.g., "admin").
     *
     * @return bool true if the user has the role
     */
    public function hasRole(string $userId, string $roleName): bool;

    /**
     * Method hasAnyRole.
     *
     * Checks if a user has any of the specified roles.
     *
     * @since 1.0.0
     *
     * @param string        $userId    the user ID
     * @param array<string> $roleNames the role names to check
     *
     * @return bool true if the user has any of the roles
     */
    public function hasAnyRole(string $userId, array $roleNames): bool;

    /**
     * Method hasAllRoles.
     *
     * Checks if a user has all of the specified roles.
     *
     * @since 1.0.0
     *
     * @param string        $userId    the user ID
     * @param array<string> $roleNames the role names to check
     *
     * @return bool true if the user has all of the roles
     */
    public function hasAllRoles(string $userId, array $roleNames): bool;

    /**
     * Method getUserRoleNames.
     *
     * Gets role names for a user.
     *
     * @since 1.0.0
     *
     * @param string $userId the user ID
     *
     * @return array<string> the role names
     */
    public function getUserRoleNames(string $userId): array;

    /**
     * Method getUserPermissionNames.
     *
     * Gets permission names for a user.
     *
     * @since 1.0.0
     *
     * @param string $userId the user ID
     *
     * @return array<string> the permission names
     */
    public function getUserPermissionNames(string $userId): array;
    // #endregion
}
