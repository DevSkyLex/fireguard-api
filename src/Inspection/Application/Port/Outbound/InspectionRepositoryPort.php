<?php

declare(strict_types=1);

namespace Inspection\Application\Port\Outbound;

use Inspection\Domain\Model\Inspection\Inspection;
use Inspection\Domain\ValueObject\{InspectionId, InspectionOrganizationId};
use Shared\Application\Contract\Sorting\{SortDirection, Sorting};

/**
 * Port InspectionRepositoryPort.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface InspectionRepositoryPort
{
  // #region Methods
  /**
   * Method save.
   *
   * Persists an inspection aggregate.
   *
   * @since 1.0.0
   *
   * @param Inspection $inspection the inspection aggregate
   */
  public function save(Inspection $inspection): void;

  /**
   * Method findById.
   *
   * Finds an inspection by identifier.
   *
   * @since 1.0.0
   *
   * @param InspectionId $id the inspection identifier
   *
   * @return ?Inspection the inspection aggregate when found
   */
  public function findById(InspectionId $id): ?Inspection;

  /**
   * Method findByOrganizationId.
   *
   * Lists inspections for an organization with optional filters.
   *
   * @since 1.0.0
   *
   * @param InspectionOrganizationId $organizationId the organization identifier
   * @param ?string $equipmentId optional equipment filter
   * @param ?string $facilityId optional facility filter
   * @param ?string $result optional result filter
   * @param ?string $status optional status filter
   * @param ?string $performedAtFrom optional lower bound for performedAt
   * @param ?string $performedAtTo optional upper bound for performedAt
   * @param ?string $inspectorUserId optional inspector user filter
   * @param ?string $checklistId optional checklist filter
   * @param ?string $search optional text search applied before pagination
   * @param Sorting $sorting requested sorting applied before pagination
   * @param int $limit maximum number of results
   * @param int $offset result offset
   *
   * @return list<Inspection> the inspection list
   */
  public function findByOrganizationId(
    InspectionOrganizationId $organizationId,
    ?string $equipmentId = null,
    ?string $facilityId = null,
    ?string $result = null,
    ?string $status = null,
    ?string $performedAtFrom = null,
    ?string $performedAtTo = null,
    ?string $inspectorUserId = null,
    ?string $checklistId = null,
    ?string $search = null,
    Sorting $sorting = new Sorting('createdAt', SortDirection::ASC),
    int $limit = 20,
    int $offset = 0,
  ): array;

  /**
   * Method countByOrganizationId.
   *
   * Counts inspections for an organization with optional filters.
   *
   * @since 1.0.0
   *
   * @param InspectionOrganizationId $organizationId the organization identifier
   * @param ?string $equipmentId optional equipment filter
   * @param ?string $facilityId optional facility filter
   * @param ?string $result optional result filter
   * @param ?string $status optional status filter
   * @param ?string $performedAtFrom optional lower bound for performedAt
   * @param ?string $performedAtTo optional upper bound for performedAt
   * @param ?string $inspectorUserId optional inspector user filter
   * @param ?string $checklistId optional checklist filter
   * @param ?string $search optional text search applied before counting
   *
   * @return int the inspection count
   */
  public function countByOrganizationId(
    InspectionOrganizationId $organizationId,
    ?string $equipmentId = null,
    ?string $facilityId = null,
    ?string $result = null,
    ?string $status = null,
    ?string $performedAtFrom = null,
    ?string $performedAtTo = null,
    ?string $inspectorUserId = null,
    ?string $checklistId = null,
    ?string $search = null,
  ): int;
  // #endregion
}
