<?php

declare(strict_types=1);

namespace Facility\Application\Port\Outbound;

use Facility\Application\Contract\Facility\FacilityListCriteria;
use Facility\Domain\Model\Facility\Facility;
use Facility\Domain\ValueObject\{FacilityId, FacilityOrganizationId};
use Shared\Application\Contract\Sorting\{SortDirection, Sorting};

/** Organization-scoped facility listings, labels and dashboard statistics. */
interface FacilityOrganizationQueryPort
{
  // #region Methods
  /**
   * Method countByOrganizationId.
   *
   * Counts facilities for an organization with optional filters.
   *
   * @since 1.0.0
   *
   * @param FacilityOrganizationId $organizationId the organization identifier
   * @param bool $includeArchived whether archived facilities are included by default when no explicit status filter is provided
   * @param FacilityListCriteria $criteria filters applied before counting
   *
   * @return int the facilities count
   */
  public function countByOrganizationId(
    FacilityOrganizationId $organizationId,
    bool $includeArchived = false,
    FacilityListCriteria $criteria = new FacilityListCriteria(),
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
   * Method getFacilityNamesByIds.
   *
   * Resolves facility display names for a bounded set of identifiers,
   * scoped to the organization.
   *
   * @since 1.0.0
   *
   * @param FacilityOrganizationId $organizationId the organization identifier
   * @param list<string> $facilityIds the facility identifiers to resolve
   *
   * @return array<string, string> map of facilityId => name
   */
  public function getFacilityNamesByIds(FacilityOrganizationId $organizationId, array $facilityIds): array;

  /**
   * Method getFacilityCodesByIds.
   *
   * Resolves facility `code` values for a bounded set of identifiers, scoped
   * to the organization — backs the CSV export's `parentCode` column, which
   * mirrors {@see self::getFacilityNamesByIds()} but for the field
   * {@see \Import\Application\Service\FacilityRowFactory} reads back on
   * import. A facility with no code is simply absent from the returned map.
   *
   * @since 1.0.0
   *
   * @param FacilityOrganizationId $organizationId the organization identifier
   * @param list<string> $facilityIds the facility identifiers to resolve
   *
   * @return array<string, string> map of facilityId => code
   */
  public function getFacilityCodesByIds(FacilityOrganizationId $organizationId, array $facilityIds): array;

  /**
   * Method findByOrganizationId.
   *
   * Lists facilities for an organization with optional filters.
   *
   * @since 1.0.0
   *
   * @param FacilityOrganizationId $organizationId the organization identifier
   * @param bool $includeArchived whether archived facilities are included by default when no explicit status filter is provided
   * @param FacilityListCriteria $criteria filters applied before pagination
   * @param Sorting $sorting requested sorting applied before pagination
   * @param int $limit maximum number of results
   * @param int $offset result offset
   *
   * @return list<Facility> the facilities collection
   */
  public function findByOrganizationId(
    FacilityOrganizationId $organizationId,
    bool $includeArchived = false,
    FacilityListCriteria $criteria = new FacilityListCriteria(),
    Sorting $sorting = new Sorting('name', SortDirection::ASC),
    int $limit = 20,
    int $offset = 0,
  ): array;

  // #endregion
}
