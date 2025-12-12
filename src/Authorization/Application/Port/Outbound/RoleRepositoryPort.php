<?php

declare(strict_types=1);

namespace Authorization\Application\Port\Outbound;

use Authorization\Domain\Model\Role;
use Authorization\Domain\ValueObject\RoleId;
use Authorization\Domain\ValueObject\RoleName;
use Shared\Domain\ValueObject\TenantId;

/**
 * Interface RoleRepositoryPort
 *
 * Port for role persistence operations.
 *
 * @category Port
 * @package Authorization\Application\Port\Outbound
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface RoleRepositoryPort
{
  /**
   * Method findById
   *
   * Finds a role by its ID.
   * 
   * @access public
   * @since 1.0.0
   *
   * @param RoleId $id The role ID.
   *
   * @return Role|null The role or null if not found.
   */
  public function findById(RoleId $id): ?Role;

  /**
   * Method findByName
   *
   * Finds a role by its name.
   * 
   * @access public
   * @since 1.0.0
   *
   * @param RoleName $name The role name.
   *
   * @return Role|null The role or null if not found.
   */
  public function findByName(RoleName $name): ?Role;

  /**
   * Method findAll
   *
   * Returns all roles.
   * 
   * @access public
   * @since 1.0.0
   *
   * @param TenantId|null $tenantId Optional tenant filter.
   *
   * @return array<Role> All roles.
   */
  public function findAll(?TenantId $tenantId = null): array;

  /**
   * Method save
   *
   * Persists a role.
   * 
   * @access public
   * @since 1.0.0
   *
   * @param Role $role The role to save.
   *
   * @return void No return value.
   */
  public function save(Role $role): void;

  /**
   * Method delete
   *
   * Deletes a role.
   * 
   * @access public
   * @since 1.0.0
   *
   * @param Role $role The role to delete.
   *
   * @return void No return value.
   */
  public function delete(Role $role): void;
}
