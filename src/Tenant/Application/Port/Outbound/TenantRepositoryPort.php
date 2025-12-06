<?php

declare(strict_types=1);

namespace Tenant\Application\Port\Outbound;

use Tenant\Domain\Model\Tenant;
use Tenant\Domain\ValueObject\TenantId;

/**
 * Interface TenantRepositoryPort
 *
 * Port for Tenant persistence.
 *
 * @category Port
 * @package Tenant\Application\Port\Outbound
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface TenantRepositoryPort
{
  //#region Methods
  /**
   * Method save
   *
   * Saves a tenant.
   *
   * @access public
   * @since 1.0.0
   *
   * @param Tenant $tenant The tenant to save.
   *
   * @return void
   */
  public function save(Tenant $tenant): void;

  /**
   * Method findById
   *
   * Finds a tenant by ID.
   *
   * @access public
   * @since 1.0.0
   *
   * @param TenantId $id The tenant ID.
   *
   * @return Tenant|null The tenant or null if not found.
   */
  public function findById(TenantId $id): ?Tenant;

  /**
   * Method findAll
   *
   * Finds all active tenants.
   *
   * @access public
   * @since 1.0.0
   *
   * @return list<Tenant> The tenants.
   */
  public function findAll(): array;

  /**
   * Method delete
   *
   * Deletes a tenant.
   *
   * @access public
   * @since 1.0.0
   *
   * @param TenantId $id The tenant ID.
   *
   * @return void
   */
  public function delete(TenantId $id): void;
  //#endregion
}
