<?php

declare(strict_types=1);

namespace Tenant\Application\Port\Outbound;

use Tenant\Domain\Model\Tenant\Tenant;
use Tenant\Domain\ValueObject\TenantId;

/**
 * Interface TenantRepositoryPort.
 *
 * Port for Tenant persistence.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface TenantRepositoryPort
{
  // #region Methods
  /**
   * Method save.
   *
   * Saves a tenant.
   *
   * @since 1.0.0
   *
   * @param Tenant $tenant the tenant to save
   */
  public function save(Tenant $tenant): void;

  /**
   * Method findById.
   *
   * Finds a tenant by ID.
   *
   * @since 1.0.0
   *
   * @param TenantId $id the tenant ID
   *
   * @return Tenant|null the tenant or null if not found
   */
  public function findById(TenantId $id): ?Tenant;

  /**
   * Method findAll.
   *
   * Finds all active tenants.
   *
   * @since 1.0.0
   *
   * @return list<Tenant> the tenants
   */
  public function findAll(): array;

  /**
   * Method delete.
   *
   * Deletes a tenant.
   *
   * @since 1.0.0
   *
   * @param TenantId $id the tenant ID
   */
  public function delete(TenantId $id): void;
  // #endregion
}
