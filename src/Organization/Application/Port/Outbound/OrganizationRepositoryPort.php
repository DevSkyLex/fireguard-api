<?php

declare(strict_types=1);

namespace Organization\Application\Port\Outbound;

use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\ValueObject\OrganizationId;

/**
 * Port OrganizationRepositoryPort.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface OrganizationRepositoryPort
{
  // #region Methods
  /**
   * Method save.
   *
   * Persists an organization aggregate.
   *
   * @since 1.0.0
   *
   * @param Organization $organization the organization aggregate
   */
  public function save(Organization $organization): void;

  /**
   * Method findById.
   *
   * Finds an organization by identifier.
   *
   * @since 1.0.0
   *
   * @param OrganizationId $id the organization identifier
   *
   * @return ?Organization the organization aggregate when found
   */
  public function findById(OrganizationId $id): ?Organization;

  /**
   * Method findByIds.
   *
   * Finds organizations by a list of identifiers.
   *
   * @since 1.0.0
   *
   * @param list<OrganizationId> $ids the organization identifiers
   *
   * @return list<Organization> the matching organizations
   */
  public function findByIds(array $ids): array;

  /**
   * Method delete.
   *
   * Deletes an organization by identifier.
   *
   * @since 1.0.0
   *
   * @param OrganizationId $id the organization identifier
   */
  public function delete(OrganizationId $id): void;
  // #endregion
}
