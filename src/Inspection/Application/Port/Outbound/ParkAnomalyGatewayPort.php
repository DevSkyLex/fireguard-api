<?php

declare(strict_types=1);

namespace Inspection\Application\Port\Outbound;

use Inspection\Application\Contract\Park\{ParkAnomaliesCounts, ParkAnomalyEntry};
use Shared\Application\Contract\Pagination\Pagination;
use Shared\Application\Contract\Sorting\Sorting;

/**
 * Port ParkAnomalyGatewayPort.
 *
 * Reads findings from published organization inspections. Listing, counting and
 * summary share the same unresolved predicates and resolved equipment scope.
 *
 * @category Port
 */
interface ParkAnomalyGatewayPort
{
  // #region Methods
  /**
   * Method findCandidateEquipmentIds.
   *
   * @access public
   *
   * @param string $organizationId owning organization
   *
   * @return list<string> equipment referenced by published inspections with unresolved findings
   */
  public function findCandidateEquipmentIds(string $organizationId): array;

  /**
   * Method list.
   *
   * @access public
   *
   * @param string $organizationId owning organization
   * @param list<string> $equipmentIds already resolved published equipment scope
   * @param Pagination $pagination applied after scoping
   * @param Sorting $sorting whitelisted field and stable tie-breaker
   *
   * @return list<ParkAnomalyEntry> page of unresolved findings
   */
  public function list(string $organizationId, array $equipmentIds, Pagination $pagination, Sorting $sorting): array;

  /**
   * Method count.
   *
   * @access public
   *
   * @param string $organizationId owning organization
   * @param list<string> $equipmentIds resolved equipment scope
   *
   * @return int total before pagination
   */
  public function count(string $organizationId, array $equipmentIds): int;

  /**
   * Method summary.
   *
   * @access public
   *
   * @param string $organizationId owning organization
   * @param list<string> $equipmentIds resolved equipment scope
   *
   * @return ParkAnomaliesCounts complete scoped counts
   */
  public function summary(string $organizationId, array $equipmentIds): ParkAnomaliesCounts;
  // #endregion
}
