<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Adapter\Organization;

use Inspection\Application\Contract\Inspection\{InspectionExecutionCriteria, InspectionInspectorCriteria, InspectionListCriteria};
use Inspection\Application\Port\Outbound\InspectionRepositoryPort;
use Inspection\Domain\ValueObject\{InspectionOrganizationId, InspectionResult, InspectionStatus, InspectorType};
use Organization\Application\Port\Outbound\InspectionStatisticsPort;

/**
 * Adapter InspectionStatisticsAdapter.
 *
 * Implements the Organization module's inspection statistics port
 * using the Inspection module's repository.
 *
 * @category Adapter
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InspectionStatisticsAdapter implements InspectionStatisticsPort
{
  /**
   * Method __construct
   *
   * Initializes the inspection repository used for organization statistics.
   *
   * @access public
   *
   * @param InspectionRepositoryPort $inspectionRepository inspection repository
   *
   * @return void
   */
  public function __construct(
    private InspectionRepositoryPort $inspectionRepository,
  ) {
  }

  /**
   * Method countInspections
   *
   * Counts inspections for an organization with optional filters.
   *
   * @access public
   *
   * @param string $organizationId the organization whose inspection or finding metrics are requested
   * @param ?string $status the optional lifecycle status filter
   * @param ?string $result the optional inspection result filter
   * @param ?string $inspectorType the optional inspector type filter
   *
   * @return int
   */
  public function countInspections(
    string $organizationId,
    ?string $status = null,
    ?string $result = null,
    ?string $inspectorType = null,
  ): int {
    return $this->inspectionRepository->countByOrganizationId(
      InspectionOrganizationId::fromString($organizationId),
      new InspectionListCriteria(
        execution: new InspectionExecutionCriteria(result: $result, status: $status),
        inspector: new InspectionInspectorCriteria(type: $inspectorType),
      ),
    );
  }

  /**
   * {@inheritDoc}
   */
  public function countActiveInspections(string $organizationId): int
  {
    $organization = InspectionOrganizationId::fromString($organizationId);

    $total = $this->inspectionRepository->countByOrganizationId($organization);
    $cancelled = $this->inspectionRepository->countByOrganizationId(
      $organization,
      new InspectionListCriteria(execution: new InspectionExecutionCriteria(status: InspectionStatus::CANCELLED->value)),
    );

    return $total - $cancelled;
  }

  /**
   * Method countInspectionOverview
   *
   * Returns aggregate inspection overview counts for dashboard cards.
   *
   * @access public
   *
   * @param string $organizationId the organization whose inspection or finding metrics are requested
   * @param ?string $status the optional lifecycle status filter
   * @param ?string $result the optional inspection result filter
   * @param ?string $inspectorType the optional inspector type filter
   *
   * @return array{total: int, draft: int, submitted: int, closed: int, cancelled: int, pass: int, fail: int, partial: int}
   */
  public function countInspectionOverview(
    string $organizationId,
    ?string $status = null,
    ?string $result = null,
    ?string $inspectorType = null,
  ): array {
    /** @var array<string, int> $overview */
    $overview = $this->inspectionRepository->countOverviewByOrganizationId(
      InspectionOrganizationId::fromString($organizationId),
      status: $status,
      result: $result,
      inspectorType: $inspectorType,
    );

    foreach (InspectionStatus::cases() as $case) {
      if (!isset($overview[$case->value])) {
        $overview[$case->value] = 0;
      }
    }
    foreach (InspectionResult::cases() as $case) {
      if (!isset($overview[$case->value])) {
        $overview[$case->value] = 0;
      }
    }

    /** @var array{total: int, draft: int, submitted: int, closed: int, cancelled: int, pass: int, fail: int, partial: int} $overview */
    return $overview;
  }

  /**
   * Method countInspectionsByStatus
   *
   * Returns inspection counts grouped by status.
   *
   * @access public
   *
   * @param string $organizationId the organization whose inspection or finding metrics are requested
   *
   * @return array<string, int> map of status => count
   */
  public function countInspectionsByStatus(string $organizationId): array
  {
    $counts = $this->inspectionRepository->countByStatusForOrganizationId(
      InspectionOrganizationId::fromString($organizationId),
    );
    $normalizedCounts = [];

    foreach (InspectionStatus::cases() as $status) {
      $normalizedCounts[$status->value] = $counts[$status->value] ?? 0;
    }

    return $normalizedCounts;
  }

  /**
   * Method countInspectionsByResult
   *
   * Returns inspection counts grouped by result.
   *
   * @access public
   *
   * @param string $organizationId the organization whose inspection or finding metrics are requested
   *
   * @return array<string, int> map of result => count
   */
  public function countInspectionsByResult(string $organizationId): array
  {
    $counts = $this->inspectionRepository->countByResultForOrganizationId(
      InspectionOrganizationId::fromString($organizationId),
    );
    $normalizedCounts = [];

    foreach (InspectionResult::cases() as $result) {
      $normalizedCounts[$result->value] = $counts[$result->value] ?? 0;
    }

    return $normalizedCounts;
  }

  /**
   * Method countInspectionsByInspectorType
   *
   * Returns inspection counts grouped by inspector type.
   *
   * @access public
   *
   * @param string $organizationId the organization whose inspection or finding metrics are requested
   *
   * @return array<string, int> map of inspector type => count
   */
  public function countInspectionsByInspectorType(string $organizationId): array
  {
    $counts = $this->inspectionRepository->countByInspectorTypeForOrganizationId(
      InspectionOrganizationId::fromString($organizationId),
    );
    $normalizedCounts = [];

    foreach (InspectorType::cases() as $inspectorType) {
      $normalizedCounts[$inspectorType->value] = $counts[$inspectorType->value] ?? 0;
    }

    return $normalizedCounts;
  }

  /**
   * Method countInspectionsPerformedSince
   *
   * Counts inspections performed from a given lower bound.
   *
   * @access public
   *
   * @param string $organizationId the organization whose inspection or finding metrics are requested
   * @param string $performedAtFrom the inclusive lower bound for performed-at dates
   *
   * @return int
   */
  public function countInspectionsPerformedSince(string $organizationId, string $performedAtFrom): int
  {
    return $this->inspectionRepository->countByOrganizationId(
      organizationId: InspectionOrganizationId::fromString($organizationId),
      criteria: new InspectionListCriteria(execution: new InspectionExecutionCriteria(performedAtFrom: $performedAtFrom)),
    );
  }

  /**
   * Method countInspectionsBetween
   *
   * Counts inspections for a bounded period with optional filters.
   *
   * @access public
   *
   * @param string $organizationId the organization whose inspection or finding metrics are requested
   * @param string $performedAtFrom the inclusive lower bound for performed-at dates
   * @param string $performedAtTo the inclusive upper bound for performed-at dates
   * @param ?string $status the optional lifecycle status filter
   * @param ?string $result the optional inspection result filter
   * @param ?string $inspectorType the optional inspector type filter
   *
   * @return int
   */
  public function countInspectionsBetween(
    string $organizationId,
    string $performedAtFrom,
    string $performedAtTo,
    ?string $status = null,
    ?string $result = null,
    ?string $inspectorType = null,
  ): int {
    return $this->inspectionRepository->countByOrganizationId(
      organizationId: InspectionOrganizationId::fromString($organizationId),
      criteria: new InspectionListCriteria(
        execution: new InspectionExecutionCriteria($result, $status, $performedAtFrom, $performedAtTo),
        inspector: new InspectionInspectorCriteria(type: $inspectorType),
      ),
    );
  }

  /**
   * Method countInspectionPeriodMetrics
   *
   * Returns aggregate inspection counts for a bounded dashboard period.
   *
   * @access public
   *
   * @param string $organizationId the organization whose inspection or finding metrics are requested
   * @param string $performedAtFrom the inclusive lower bound for performed-at dates
   * @param string $performedAtTo the inclusive upper bound for performed-at dates
   * @param ?string $status the optional lifecycle status filter
   * @param ?string $result the optional inspection result filter
   * @param ?string $inspectorType the optional inspector type filter
   *
   * @return array{total: int, closed: int, pass: int, fail: int, partial: int}
   */
  public function countInspectionPeriodMetrics(
    string $organizationId,
    string $performedAtFrom,
    string $performedAtTo,
    ?string $status = null,
    ?string $result = null,
    ?string $inspectorType = null,
  ): array {
    return $this->inspectionRepository->countPeriodMetricsByOrganizationId(
      organizationId: InspectionOrganizationId::fromString($organizationId),
      performedAtFrom: $performedAtFrom,
      performedAtTo: $performedAtTo,
      status: $status,
      result: $result,
      inspectorType: $inspectorType,
    );
  }

  /**
   * Method countInspectionsPerformedByDay
   *
   * Returns inspection counts grouped by performed day for a period.
   *
   * @access public
   *
   * @param string $organizationId the organization whose inspection or finding metrics are requested
   * @param string $performedAtFrom the inclusive lower bound for performed-at dates
   * @param string $performedAtTo the inclusive upper bound for performed-at dates
   * @param ?string $timeZone the timezone used to group performed dates
   * @param ?string $status the optional lifecycle status filter
   * @param ?string $result the optional inspection result filter
   * @param ?string $inspectorType the optional inspector type filter
   *
   * @return array<string, int> map of YYYY-MM-DD => count
   */
  public function countInspectionsPerformedByDay(
    string $organizationId,
    string $performedAtFrom,
    string $performedAtTo,
    ?string $timeZone = null,
    ?string $status = null,
    ?string $result = null,
    ?string $inspectorType = null,
  ): array {
    return $this->inspectionRepository->countByPerformedDayForOrganizationId(
      organizationId: InspectionOrganizationId::fromString($organizationId),
      performedAtFrom: $performedAtFrom,
      performedAtTo: $performedAtTo,
      timeZone: $timeZone,
      status: $status,
      result: $result,
      inspectorType: $inspectorType,
    );
  }
}
