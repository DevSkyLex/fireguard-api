<?php

declare(strict_types=1);

namespace Authorization\Application\Port\Outbound;

use Authorization\Domain\Model\Permission;
use Authorization\Domain\ValueObject\PermissionId;
use Authorization\Domain\ValueObject\PermissionName;

/**
 * Interface PermissionRepositoryPort
 *
 * Port for permission persistence operations.
 *
 * @category Port
 * @package Authorization\Application\Port\Outbound
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface PermissionRepositoryPort
{
  //#region Methods
  /**
   * Method findById
   *
   * Finds a permission by its ID.
   *
   * @access public
   * @since 1.0.0
   *
   * @param PermissionId $id The permission ID.
   *
   * @return Permission|null The permission or null if not found.
   */
  public function findById(PermissionId $id): ?Permission;

  /**
   * Method findByName
   *
   * Finds a permission by its name.
   *
   * @access public
   * @since 1.0.0
   *
   * @param PermissionName $name The permission name.
   *
   * @return Permission|null The permission or null if not found.
   */
  public function findByName(PermissionName $name): ?Permission;

  /**
   * Method findAll
   *
   * Returns all permissions.
   *
   * @access public
   * @since 1.0.0
   *
   * @return array<Permission> All permissions.
   */
  public function findAll(): array;

  /**
   * Method save
   *
   * Persists a permission.
   *
   * @access public
   * @since 1.0.0
   *
   * @param Permission $permission The permission to save.
   *
   * @return void No return value.
   */
  public function save(Permission $permission): void;

  /**
   * Method delete
   *
   * Deletes a permission.
   *
   * @access public
   * @since 1.0.0
   *
   * @param Permission $permission The permission to delete.
   *
   * @return void No return value.
   */
  public function delete(Permission $permission): void;
  //#endregion
}
