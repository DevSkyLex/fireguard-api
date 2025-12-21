<?php

declare(strict_types=1);

namespace Authorization\Application\Port\Outbound;

use Authorization\Domain\Model\Role;
use Authorization\Domain\ValueObject\RoleId;
use Authorization\Domain\ValueObject\RoleName;
use Shared\Domain\ValueObject\TenantId;

/**
 * Interface RoleRepositoryPort.
 *
 * Port for role persistence operations.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface RoleRepositoryPort
{
  /**
   * Method findById.
   *
   * Finds a role by its ID.
   *
   * @since 1.0.0
   *
   * @param RoleId $id the role ID
   *
   * @return Role|null the role or null if not found
   */
  public function findById(RoleId $id): ?Role;

  /**
   * Method findByName.
   *
   * Finds a role by its name.
   *
   * @since 1.0.0
   *
   * @param RoleName $name the role name
   *
   * @return Role|null the role or null if not found
   */
  public function findByName(RoleName $name): ?Role;

  /**
   * Method findAll.
   *
   * Returns all roles.
   *
   * @since 1.0.0
   *
   * @param TenantId|null $tenantId optional tenant filter
   *
   * @return array<Role> all roles
   */
  public function findAll(?TenantId $tenantId = null): array;

  /**
   * Method save.
   *
   * Persists a role.
   *
   * @since 1.0.0
   *
   * @param Role $role the role to save
   *
   * @return void no return value
   */
  public function save(Role $role): void;

  /**
   * Method delete.
   *
   * Deletes a role.
   *
   * @since 1.0.0
   *
   * @param Role $role the role to delete
   *
   * @return void no return value
   */
  public function delete(Role $role): void;
}
