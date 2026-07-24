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
   * Method remove.
   *
   * Removes an inspection aggregate.
   *
   * @since 1.0.0
   *
   * @param Inspection $inspection the inspection aggregate
   */
  public function remove(Inspection $inspection): void;

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
   * Method findPublishedById.
   *
   * Finds a PUBLISHED inspection by identifier. Draft intervention
   * scratchpads are invisible to this lookup: the lifecycle commands
   * (submit, close, cancel) must not act on — nor audit — records that
   * only exist inside an unpublished intervention.
   *
   * @since 1.0.0
   *
   * @param InspectionId $id the inspection identifier
   *
   * @return ?Inspection the published inspection aggregate when found
   */
  public function findPublishedById(InspectionId $id): ?Inspection;

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
   * @param ?string $inspectorType optional inspector type filter
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
    ?string $inspectorType = null,
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
   * @param ?string $inspectorType optional inspector type filter
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
    ?string $inspectorType = null,
    ?string $checklistId = null,
    ?string $search = null,
  ): int;

  /**
   * Method findEquipmentIdsByIds.
   *
   * Resolves the equipment identifier of each given inspection, in one round
   * trip. Backs read-side listings that need to name what an inspection
   * belonging to a non-conformity was performed on, without hydrating the
   * full inspection aggregate row by row.
   *
   * Identifiers that cannot be resolved are absent from the result.
   *
   * @since 1.0.0
   *
   * @param list<string> $inspectionIds the inspection identifiers to resolve
   *
   * @return array<string, string> equipment identifier keyed by inspection identifier
   */
  public function findEquipmentIdsByIds(array $inspectionIds): array;

  /**
   * Counts dashboard overview metrics for inspections in one query.
   *
   * @return array{total: int, draft: int, submitted: int, closed: int, pass: int, fail: int, partial: int}
   */
  public function countOverviewByOrganizationId(
    InspectionOrganizationId $organizationId,
    ?string $status = null,
    ?string $result = null,
    ?string $inspectorType = null,
  ): array;

  /**
   * Counts inspections grouped by status for an organization.
   *
   * @return array<string, int> map of status => count
   */
  public function countByStatusForOrganizationId(InspectionOrganizationId $organizationId): array;

  /**
   * Counts inspections grouped by result for an organization.
   *
   * @return array<string, int> map of result => count
   */
  public function countByResultForOrganizationId(InspectionOrganizationId $organizationId): array;

  /**
   * Counts inspections grouped by inspector type for an organization.
   *
   * @return array<string, int> map of inspector type => count
   */
  public function countByInspectorTypeForOrganizationId(InspectionOrganizationId $organizationId): array;

  /**
   * Counts inspections grouped by performed day for an organization and period.
   *
   * @return array<string, int> map of YYYY-MM-DD => count
   */
  public function countByPerformedDayForOrganizationId(
    InspectionOrganizationId $organizationId,
    string $performedAtFrom,
    string $performedAtTo,
    ?string $timeZone = null,
    ?string $status = null,
    ?string $result = null,
    ?string $inspectorType = null,
  ): array;

  /**
   * Counts dashboard period inspection metrics in one query.
   *
   * @return array{total: int, closed: int, pass: int, fail: int, partial: int}
   */
  public function countPeriodMetricsByOrganizationId(
    InspectionOrganizationId $organizationId,
    string $performedAtFrom,
    string $performedAtTo,
    ?string $status = null,
    ?string $result = null,
    ?string $inspectorType = null,
  ): array;
  // #endregion
}
