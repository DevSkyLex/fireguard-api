<?php

declare(strict_types=1);

namespace Inspection\Application\Port\Outbound;

use Inspection\Domain\Model\NonConformity\NonConformity;
use Inspection\Domain\ValueObject\{NonConformityId, NonConformityInspectionId};
use Shared\Application\Contract\Sorting\{SortDirection, Sorting};

/**
 * Port NonConformityRepositoryPort.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface NonConformityRepositoryPort
{
  // #region Methods
  /**
   * Method save.
   *
   * Persists a non-conformity aggregate.
   *
   * @since 1.0.0
   *
   * @param NonConformity $nonConformity the non-conformity aggregate
   */
  public function save(NonConformity $nonConformity): void;

  /**
   * Method findById.
   *
   * Finds a non-conformity by identifier.
   *
   * @since 1.0.0
   *
   * @param NonConformityId $id the non-conformity identifier
   *
   * @return ?NonConformity the non-conformity aggregate when found
   */
  public function findById(NonConformityId $id): ?NonConformity;

  /**
   * Method findByInspectionId.
   *
   * Lists non-conformities for an inspection.
   *
   * @since 1.0.0
   *
   * @param NonConformityInspectionId $inspectionId the inspection identifier
   * @param ?string $severity optional severity filter
   * @param ?string $status optional status filter
   *
   * @return list<NonConformity> the non-conformity list
   */
  public function findByInspectionId(
    NonConformityInspectionId $inspectionId,
    ?string $severity = null,
    ?string $status = null,
    ?string $search = null,
    Sorting $sorting = new Sorting('createdAt', SortDirection::DESC),
    int $limit = 20,
    int $offset = 0,
  ): array;

  /**
   * Method countByInspectionId.
   *
   * Counts non-conformities for an inspection.
   *
   * @since 1.0.0
   *
   * @param NonConformityInspectionId $inspectionId the inspection identifier
   *
   * @return int the count
   */
  public function countByInspectionId(
    NonConformityInspectionId $inspectionId,
    ?string $severity = null,
    ?string $status = null,
    ?string $search = null,
  ): int;

  /**
   * Method countsByInspectionIds.
   *
   * Counts non-conformities for multiple inspections in a single query.
   *
   * @since 1.0.0
   *
   * @param list<string> $inspectionIds the inspection identifiers as strings
   *
   * @return array<string, int> map of inspectionId => count
   */
  public function countsByInspectionIds(array $inspectionIds): array;
  // #endregion
}
