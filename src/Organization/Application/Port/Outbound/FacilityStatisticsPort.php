<?php

declare(strict_types=1);

namespace Organization\Application\Port\Outbound;

/**
 * Port FacilityStatisticsPort.
 *
 * Provides facility statistics for organization dashboards without
 * coupling the Organization module to the Facility repository.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface FacilityStatisticsPort
{
  // #region Methods
  /**
   * Method countFacilities.
   *
   * Counts all facilities for an organization, including archived ones.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param ?string $type optional facility type filter
   *
   * @return int the total facility count
   */
  public function countFacilities(string $organizationId, ?string $type = null): int;

  /**
   * Method countActiveFacilities.
   *
   * Counts active (non-archived) facilities for an organization.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param ?string $type optional facility type filter
   *
   * @return int the active facility count
   */
  public function countActiveFacilities(string $organizationId, ?string $type = null): int;

  /**
   * Returns aggregate facility overview counts for dashboard cards.
   *
   * @return array{total: int, active: int}
   */
  public function countFacilityOverview(string $organizationId, ?string $type = null): array;

  /**
   * Method countFacilitiesByType.
   *
   * Counts facilities by type for an organization, including archived ones.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   *
   * @return array<string, int> map of facility type => count
   */
  public function countFacilitiesByType(string $organizationId): array;

  /**
   * Returns facility creation counts grouped by day for a period.
   *
   * @return array<string, int> map of YYYY-MM-DD => count
   */
  public function countFacilitiesCreatedByDay(
    string $organizationId,
    string $createdAtFrom,
    string $createdAtTo,
    ?string $timeZone = null,
    ?string $type = null,
  ): array;
  // #endregion
}
