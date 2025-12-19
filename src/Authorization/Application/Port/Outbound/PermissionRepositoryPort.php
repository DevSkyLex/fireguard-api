<?php

declare(strict_types=1);

namespace Authorization\Application\Port\Outbound;

use Authorization\Domain\Model\Permission;
use Authorization\Domain\ValueObject\PermissionId;
use Authorization\Domain\ValueObject\PermissionName;

/**
 * Interface PermissionRepositoryPort.
 *
 * Port for permission persistence operations.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface PermissionRepositoryPort
{
    // #region Methods
    /**
     * Method findById.
     *
     * Finds a permission by its ID.
     *
     * @since 1.0.0
     *
     * @param PermissionId $id the permission ID
     *
     * @return Permission|null the permission or null if not found
     */
    public function findById(PermissionId $id): ?Permission;

    /**
     * Method findByName.
     *
     * Finds a permission by its name.
     *
     * @since 1.0.0
     *
     * @param PermissionName $name the permission name
     *
     * @return Permission|null the permission or null if not found
     */
    public function findByName(PermissionName $name): ?Permission;

    /**
     * Method findAll.
     *
     * Returns all permissions.
     *
     * @since 1.0.0
     *
     * @return array<Permission> all permissions
     */
    public function findAll(): array;

    /**
     * Method save.
     *
     * Persists a permission.
     *
     * @since 1.0.0
     *
     * @param Permission $permission the permission to save
     *
     * @return void no return value
     */
    public function save(Permission $permission): void;

    /**
     * Method delete.
     *
     * Deletes a permission.
     *
     * @since 1.0.0
     *
     * @param Permission $permission the permission to delete
     *
     * @return void no return value
     */
    public function delete(Permission $permission): void;
    // #endregion
}
