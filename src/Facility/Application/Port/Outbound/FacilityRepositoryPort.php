<?php

declare(strict_types=1);

namespace Facility\Application\Port\Outbound;

use Facility\Domain\Model\Facility\Facility;
use Facility\Domain\ValueObject\{FacilityId, FacilityOrganizationId};
use Shared\Application\Contract\Sorting\{SortDirection, Sorting};

/**
 * Port FacilityRepositoryPort.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface FacilityRepositoryPort
{
  // #region Methods
  /**
   * Method save.
   *
   * Persists a facility aggregate.
   *
   * @since 1.0.0
   *
   * @param Facility $facility the facility aggregate
   */
  public function save(Facility $facility): void;

  /**
   * Method findById.
   *
   * Finds a facility by identifier.
   *
   * @since 1.0.0
   *
   * @param FacilityId $id the facility identifier
   *
   * @return ?Facility the facility aggregate when found
   */
  public function findById(FacilityId $id): ?Facility;

  /**
   * Method findChildren.
   *
   * Lists direct children for a facility.
   *
   * @return list<Facility>
   */
  public function findChildren(
    FacilityOrganizationId $organizationId,
    FacilityId $facilityId,
    bool $includeArchived = false,
    ?string $search = null,
    Sorting $sorting = new Sorting('name', SortDirection::ASC),
    int $limit = 20,
    int $offset = 0,
  ): array;

  /**
   * Counts direct children for a facility.
   */
  public function countChildren(
    FacilityOrganizationId $organizationId,
    FacilityId $facilityId,
    bool $includeArchived = false,
    ?string $search = null,
  ): int;

  /**
   * Counts direct children grouped by parent facility identifier.
   *
   * @param list<FacilityId> $parentIds
   *
   * @return array<string, int>
   */
  public function countChildrenByParentIds(
    FacilityOrganizationId $organizationId,
    array $parentIds,
    bool $includeArchived = false,
  ): array;

  /**
   * Method findDescendants.
   *
   * Lists all descendants for a facility.
   *
   * @return list<Facility>
   */
  public function findDescendants(
    FacilityOrganizationId $organizationId,
    FacilityId $facilityId,
    bool $includeArchived = false,
    ?string $search = null,
    Sorting $sorting = new Sorting('name', SortDirection::ASC),
  ): array;

  /**
   * Method countByOrganizationId.
   *
   * Counts facilities for an organization with optional filters.
   *
   * @since 1.0.0
   *
   * @param FacilityOrganizationId $organizationId the organization identifier
   * @param bool $includeArchived whether archived facilities are included by default when no explicit status filter is provided
   * @param ?string $type optional type filter
   * @param ?string $status optional status filter
   * @param ?string $parentFacilityId optional parent facility filter
   * @param ?string $code optional exact code filter
   * @param ?string $search optional text search applied before counting
   * @param bool $rootsOnly whether only facilities without parent are counted
   *
   * @return int the facilities count
   */
  public function countByOrganizationId(
    FacilityOrganizationId $organizationId,
    bool $includeArchived = false,
    ?string $type = null,
    ?string $status = null,
    ?string $parentFacilityId = null,
    ?string $code = null,
    ?string $search = null,
    bool $rootsOnly = false,
  ): int;

  /**
   * Method countActiveByOrganizationId.
   *
   * Counts active (non-archived) facilities belonging to an organization.
   *
   * @since 1.0.0
   *
   * @param FacilityOrganizationId $organizationId the organization identifier
   *
   * @return int the active facility count
   */
  public function countActiveByOrganizationId(FacilityOrganizationId $organizationId): int;

  /**
   * Counts dashboard overview metrics for facilities in one query.
   *
   * @return array{total: int, active: int}
   */
  public function countOverviewByOrganizationId(
    FacilityOrganizationId $organizationId,
    ?string $type = null,
  ): array;

  /**
   * Counts facilities grouped by type for an organization.
   *
   * @since 1.0.0
   *
   * @param FacilityOrganizationId $organizationId the organization identifier
   * @param bool $includeArchived whether archived facilities are included
   *
   * @return array<string, int> map of type => count
   */
  public function countByTypeForOrganizationId(
    FacilityOrganizationId $organizationId,
    bool $includeArchived = false,
  ): array;

  /**
   * Counts facilities grouped by creation day for an organization.
   *
   * @return array<string, int> map of YYYY-MM-DD => count
   */
  public function countByCreatedDayForOrganizationId(
    FacilityOrganizationId $organizationId,
    string $createdAtFrom,
    string $createdAtTo,
    ?string $timeZone = null,
    ?string $type = null,
  ): array;

  /**
   * Method findByOrganizationId.
   *
   * Lists facilities for an organization with optional filters.
   *
   * @since 1.0.0
   *
   * @param FacilityOrganizationId $organizationId the organization identifier
   * @param bool $includeArchived whether archived facilities are included by default when no explicit status filter is provided
   * @param ?string $type optional type filter
   * @param ?string $status optional status filter
   * @param ?string $parentFacilityId optional parent facility filter
   * @param ?string $code optional exact code filter
   * @param ?string $search optional text search applied before pagination
   * @param Sorting $sorting requested sorting applied before pagination
   * @param int $limit maximum number of results
   * @param int $offset result offset
   * @param bool $rootsOnly whether only facilities without parent are listed
   *
   * @return list<Facility> the facilities collection
   */
  public function findByOrganizationId(
    FacilityOrganizationId $organizationId,
    bool $includeArchived = false,
    ?string $type = null,
    ?string $status = null,
    ?string $parentFacilityId = null,
    ?string $code = null,
    ?string $search = null,
    Sorting $sorting = new Sorting('name', SortDirection::ASC),
    int $limit = 20,
    int $offset = 0,
    bool $rootsOnly = false,
  ): array;

  // #endregion
}
