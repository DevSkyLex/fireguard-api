<?php

declare(strict_types=1);

namespace Organization\Application\Port\Outbound;

use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationStatus};
use Shared\Application\Contract\Sorting\{SortDirection, Sorting};

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
   * Method statusOf.
   *
   * Reads an organization's status without hydrating the aggregate.
   *
   * This exists for the authorization hot path, which asks the question on
   * every permission check and must not pay a full hydration for it.
   *
   * @since 1.2.0
   *
   * @param OrganizationId $id the organization identifier
   *
   * @return OrganizationStatus|null the status, or null when the organization does not exist
   */
  public function statusOf(OrganizationId $id): ?OrganizationStatus;

  /**
   * Method findByIds.
   *
   * Finds organizations by a list of identifiers with optional filters.
   *
   * @since 1.0.0
   *
   * @param list<OrganizationId> $ids the organization identifiers
   * @param ?string $status optional status filter
   * @param ?string $search optional text search applied before pagination
   * @param Sorting $sorting requested sorting applied before pagination
   * @param int $limit maximum number of results
   * @param int $offset result offset
   *
   * @return list<Organization> the matching organizations
   */
  public function findByIds(
    array $ids,
    ?string $status = null,
    ?string $search = null,
    Sorting $sorting = new Sorting('name', SortDirection::ASC),
    int $limit = 20,
    int $offset = 0,
  ): array;

  /**
   * Method countByIds.
   *
   * Counts organizations by a list of identifiers with optional filters.
   *
   * @since 1.0.0
   *
   * @param list<OrganizationId> $ids the organization identifiers
   * @param ?string $status optional status filter
   * @param ?string $search optional text search applied before counting
   *
   * @return int the matching organization count
   */
  public function countByIds(array $ids, ?string $status = null, ?string $search = null): int;

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
