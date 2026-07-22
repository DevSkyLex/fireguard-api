<?php

declare(strict_types=1);

namespace Inspection\Application\Port\Outbound;

use Inspection\Domain\Model\NonConformity\NonConformity;
use Inspection\Domain\ValueObject\{InspectionOrganizationId, NonConformityId, NonConformityInspectionId};
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

  /**
   * Method findByOrganizationId.
   *
   * Lists non-conformities across every inspection of an organization,
   * newest first by default. Backs the organization-wide compliance
   * collection endpoint — org-scoping comes from the join to the inspection
   * and organization records, never from trusting a caller-supplied ID.
   *
   * @since 1.0.0
   *
   * @param InspectionOrganizationId $organizationId the organization identifier
   * @param ?string $severity optional severity filter
   * @param ?string $status optional status filter
   * @param ?string $search optional text search applied before pagination
   * @param Sorting $sorting requested sorting applied before pagination
   * @param int $limit maximum number of results
   * @param int $offset result offset
   *
   * @return list<NonConformity> the non-conformity list
   */
  public function findByOrganizationId(
    InspectionOrganizationId $organizationId,
    ?string $severity = null,
    ?string $status = null,
    ?string $search = null,
    Sorting $sorting = new Sorting('createdAt', SortDirection::DESC),
    int $limit = 20,
    int $offset = 0,
  ): array;

  /**
   * Method countByOrganizationId.
   *
   * Counts non-conformities for an organization.
   *
   * @since 1.0.0
   *
   * @param InspectionOrganizationId $organizationId the organization identifier
   * @param ?string $severity optional severity filter
   * @param ?string $status optional status filter
   * @param ?string $search optional text search applied before counting
   *
   * @return int the count
   */
  public function countByOrganizationId(
    InspectionOrganizationId $organizationId,
    ?string $severity = null,
    ?string $status = null,
    ?string $search = null,
  ): int;

  /**
   * Counts dashboard overview metrics for non-conformities in one query.
   *
   * @return array{total: int, open: int, in_progress: int, done: int, waived: int, overdue: int, critical_open: int}
   */
  public function countOverviewByOrganizationId(
    InspectionOrganizationId $organizationId,
    string $dueAtBefore,
    ?string $severity = null,
    ?string $status = null,
  ): array;

  /**
   * Counts non-conformities grouped by status for an organization.
   *
   * @return array<string, int> map of status => count
   */
  public function countByStatusForOrganizationId(InspectionOrganizationId $organizationId): array;

  /**
   * Counts non-conformities grouped by severity for an organization.
   *
   * @return array<string, int> map of severity => count
   */
  public function countBySeverityForOrganizationId(InspectionOrganizationId $organizationId): array;

  /**
   * Counts overdue open non-conformities for an organization.
   */
  public function countOverdueByOrganizationId(
    InspectionOrganizationId $organizationId,
    string $dueAtBefore,
    ?string $severity = null,
    ?string $status = null,
  ): int;

  /**
   * Counts non-conformities that were active at the given instant.
   */
  public function countActiveByOrganizationIdAtDate(
    InspectionOrganizationId $organizationId,
    string $at,
    ?string $severity = null,
    ?string $status = null,
  ): int;

  /**
   * Counts non-conformities grouped by creation day for an organization and period.
   *
   * @return array<string, int> map of YYYY-MM-DD => count
   */
  public function countByCreatedDayForOrganizationId(
    InspectionOrganizationId $organizationId,
    string $createdAtFrom,
    string $createdAtTo,
    ?string $timeZone = null,
    ?string $severity = null,
    ?string $status = null,
  ): array;

  /**
   * Counts non-conformities grouped by resolution day for an organization and period.
   *
   * @return array<string, int> map of YYYY-MM-DD => count
   */
  public function countByResolvedDayForOrganizationId(
    InspectionOrganizationId $organizationId,
    string $resolvedAtFrom,
    string $resolvedAtTo,
    ?string $timeZone = null,
    ?string $severity = null,
    ?string $status = null,
  ): array;

  /**
   * Counts dashboard period non-conformity metrics in one query.
   *
   * @return array{opened: int, resolved: int, activeAtStart: int}
   */
  public function countPeriodMetricsByOrganizationId(
    InspectionOrganizationId $organizationId,
    string $periodFrom,
    string $periodTo,
    string $activeAt,
    ?string $severity = null,
    ?string $status = null,
  ): array;

  /**
   * Counts critical non-conformities that are still open or in progress.
   */
  public function countOpenCriticalByOrganizationId(InspectionOrganizationId $organizationId, ?string $status = null): int;
  // #endregion
}
