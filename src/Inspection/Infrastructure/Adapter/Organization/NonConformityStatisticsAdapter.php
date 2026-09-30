<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Adapter\Organization;

use Inspection\Application\Port\Outbound\NonConformityRepositoryPort;
use Inspection\Domain\Model\NonConformity\NonConformity;
use Inspection\Domain\ValueObject\{InspectionOrganizationId, NonConformitySeverity, NonConformityStatus};
use Organization\Application\Contract\Inspection\OpenNonConformitySummary;
use Organization\Application\Port\Outbound\NonConformityStatisticsPort;
use Shared\Application\Contract\Sorting\{SortDirection, Sorting};

use function array_map;
use function array_slice;
use function max;
use function usort;

/**
 * Adapter NonConformityStatisticsAdapter.
 *
 * Implements the Organization module's non-conformity statistics port
 * using the Inspection module's repository.
 *
 * @category Adapter
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class NonConformityStatisticsAdapter implements NonConformityStatisticsPort
{
  /**
   * Method __construct
   *
   * Initializes the non-conformity repository used for organization statistics.
   *
   * @access public
   *
   * @param NonConformityRepositoryPort $nonConformityRepository non-conformity repository
   *
   * @return void
   */
  public function __construct(
    private NonConformityRepositoryPort $nonConformityRepository,
  ) {
  }

  /**
   * Method countNonConformities
   *
   * Counts non-conformities for an organization with optional severity/status filters.
   *
   * @access public
   *
   * @param string $organizationId the organization whose inspection or finding metrics are requested
   * @param ?string $severity the optional finding severity filter
   * @param ?string $status the optional lifecycle status filter
   *
   * @return int
   */
  public function countNonConformities(string $organizationId, ?string $severity = null, ?string $status = null): int
  {
    return $this->nonConformityRepository->countByOrganizationId(
      InspectionOrganizationId::fromString($organizationId),
      severity: $severity,
      status: $status,
    );
  }

  /**
   * Method countNonConformityOverview
   *
   * Returns aggregate non-conformity overview counts for dashboard cards and alerts.
   *
   * @access public
   *
   * @param string $organizationId the organization whose inspection or finding metrics are requested
   * @param string $dueAtBefore the cutoff used to identify overdue findings
   * @param ?string $severity the optional finding severity filter
   * @param ?string $status the optional lifecycle status filter
   *
   * @return array{total: int, open: int, in_progress: int, done: int, waived: int, overdue: int, critical_open: int}
   */
  public function countNonConformityOverview(
    string $organizationId,
    string $dueAtBefore,
    ?string $severity = null,
    ?string $status = null,
  ): array {
    /** @var array<string, int> $overview */
    $overview = $this->nonConformityRepository->countOverviewByOrganizationId(
      organizationId: InspectionOrganizationId::fromString($organizationId),
      dueAtBefore: $dueAtBefore,
      severity: $severity,
      status: $status,
    );

    foreach (NonConformityStatus::cases() as $case) {
      if (!isset($overview[$case->value])) {
        $overview[$case->value] = 0;
      }
    }

    /** @var array{total: int, open: int, in_progress: int, done: int, waived: int, overdue: int, critical_open: int} $overview */
    return $overview;
  }

  /**
   * Method countNonConformitiesByStatus
   *
   * Returns non-conformity counts grouped by status.
   *
   * @access public
   *
   * @param string $organizationId the organization whose inspection or finding metrics are requested
   *
   * @return array<string, int> map of status => count
   */
  public function countNonConformitiesByStatus(string $organizationId): array
  {
    $counts = $this->nonConformityRepository->countByStatusForOrganizationId(
      InspectionOrganizationId::fromString($organizationId),
    );
    $normalizedCounts = [];

    foreach (NonConformityStatus::cases() as $status) {
      $normalizedCounts[$status->value] = $counts[$status->value] ?? 0;
    }

    return $normalizedCounts;
  }

  /**
   * Method countNonConformitiesBySeverity
   *
   * Returns non-conformity counts grouped by severity.
   *
   * @access public
   *
   * @param string $organizationId the organization whose inspection or finding metrics are requested
   *
   * @return array<string, int> map of severity => count
   */
  public function countNonConformitiesBySeverity(string $organizationId): array
  {
    $counts = $this->nonConformityRepository->countBySeverityForOrganizationId(
      InspectionOrganizationId::fromString($organizationId),
    );
    $normalizedCounts = [];

    foreach (NonConformitySeverity::cases() as $severity) {
      $normalizedCounts[$severity->value] = $counts[$severity->value] ?? 0;
    }

    return $normalizedCounts;
  }

  /**
   * Method countOverdueNonConformities
   *
   * Counts overdue open non-conformities for an organization.
   *
   * @access public
   *
   * @param string $organizationId the organization whose inspection or finding metrics are requested
   * @param string $dueAtBefore the cutoff used to identify overdue findings
   * @param ?string $severity the optional finding severity filter
   * @param ?string $status the optional lifecycle status filter
   *
   * @return int
   */
  public function countOverdueNonConformities(
    string $organizationId,
    string $dueAtBefore,
    ?string $severity = null,
    ?string $status = null,
  ): int {
    return $this->nonConformityRepository->countOverdueByOrganizationId(
      organizationId: InspectionOrganizationId::fromString($organizationId),
      dueAtBefore: $dueAtBefore,
      severity: $severity,
      status: $status,
    );
  }

  /**
   * Method countActiveNonConformitiesAtDate
   *
   * Counts non-conformities that were active at a given instant.
   *
   * @access public
   *
   * @param string $organizationId the organization whose inspection or finding metrics are requested
   * @param string $at the instant at which active findings are counted
   * @param ?string $severity the optional finding severity filter
   * @param ?string $status the optional lifecycle status filter
   *
   * @return int
   */
  public function countActiveNonConformitiesAtDate(
    string $organizationId,
    string $at,
    ?string $severity = null,
    ?string $status = null,
  ): int {
    return $this->nonConformityRepository->countActiveByOrganizationIdAtDate(
      organizationId: InspectionOrganizationId::fromString($organizationId),
      at: $at,
      severity: $severity,
      status: $status,
    );
  }

  /**
   * Method countNonConformityPeriodMetrics
   *
   * Returns aggregate non-conformity counts for a bounded dashboard period.
   *
   * @access public
   *
   * @param string $organizationId the organization whose inspection or finding metrics are requested
   * @param string $periodFrom the inclusive lower bound for the reporting period
   * @param string $periodTo the inclusive upper bound for the reporting period
   * @param string $activeAt the instant used to evaluate findings active in the period
   * @param ?string $severity the optional finding severity filter
   * @param ?string $status the optional lifecycle status filter
   *
   * @return array{opened: int, resolved: int, activeAtStart: int}
   */
  public function countNonConformityPeriodMetrics(
    string $organizationId,
    string $periodFrom,
    string $periodTo,
    string $activeAt,
    ?string $severity = null,
    ?string $status = null,
  ): array {
    return $this->nonConformityRepository->countPeriodMetricsByOrganizationId(
      organizationId: InspectionOrganizationId::fromString($organizationId),
      periodFrom: $periodFrom,
      periodTo: $periodTo,
      activeAt: $activeAt,
      severity: $severity,
      status: $status,
    );
  }

  /**
   * Method countNonConformitiesCreatedByDay
   *
   * Returns non-conformity creation counts grouped by day for a period.
   *
   * @access public
   *
   * @param string $organizationId the organization whose inspection or finding metrics are requested
   * @param string $createdAtFrom the inclusive lower bound for finding creation dates
   * @param string $createdAtTo the inclusive upper bound for finding creation dates
   * @param ?string $timeZone the timezone used to group performed dates
   * @param ?string $severity the optional finding severity filter
   * @param ?string $status the optional lifecycle status filter
   *
   * @return array<string, int> map of YYYY-MM-DD => count
   */
  public function countNonConformitiesCreatedByDay(
    string $organizationId,
    string $createdAtFrom,
    string $createdAtTo,
    ?string $timeZone = null,
    ?string $severity = null,
    ?string $status = null,
  ): array {
    return $this->nonConformityRepository->countByCreatedDayForOrganizationId(
      organizationId: InspectionOrganizationId::fromString($organizationId),
      createdAtFrom: $createdAtFrom,
      createdAtTo: $createdAtTo,
      timeZone: $timeZone,
      severity: $severity,
      status: $status,
    );
  }

  /**
   * Method countNonConformitiesResolvedByDay
   *
   * Returns non-conformity resolution counts grouped by day for a period.
   *
   * @access public
   *
   * @param string $organizationId the organization whose inspection or finding metrics are requested
   * @param string $resolvedAtFrom the inclusive lower bound for resolution dates
   * @param string $resolvedAtTo the inclusive upper bound for resolution dates
   * @param ?string $timeZone the timezone used to group performed dates
   * @param ?string $severity the optional finding severity filter
   * @param ?string $status the optional lifecycle status filter
   *
   * @return array<string, int> map of YYYY-MM-DD => count
   */
  public function countNonConformitiesResolvedByDay(
    string $organizationId,
    string $resolvedAtFrom,
    string $resolvedAtTo,
    ?string $timeZone = null,
    ?string $severity = null,
    ?string $status = null,
  ): array {
    return $this->nonConformityRepository->countByResolvedDayForOrganizationId(
      organizationId: InspectionOrganizationId::fromString($organizationId),
      resolvedAtFrom: $resolvedAtFrom,
      resolvedAtTo: $resolvedAtTo,
      timeZone: $timeZone,
      severity: $severity,
      status: $status,
    );
  }

  /**
   * Method countSlaBreachedNonConformities
   *
   * Method countSlaBreachedNonConformities.
   *
   * Counts the organization's unresolved non-conformities whose resolution
   * SLA breach has been signalled (the hourly SLA sweep stamped them).
   *
   * @access public
   *
   * @param string $organizationId the organization identifier
   *
   * @return int the unresolved SLA-breached non-conformity count
   */
  public function countSlaBreachedNonConformities(string $organizationId): int
  {
    return $this->nonConformityRepository->countSlaBreachedByOrganizationId(
      InspectionOrganizationId::fromString($organizationId),
    );
  }

  /**
   * Method findOpenNonConformities
   *
   * Method findOpenNonConformities.
   *
   * Lists the organization's unresolved non-conformities, oldest first.
   * Backs the weekly digest detail lines.
   *
   * @access public
   *
   * @param string $organizationId the organization identifier
   * @param int $limit maximum number of summaries to return
   *
   * @return list<OpenNonConformitySummary> the unresolved non-conformity summaries
   */
  public function findOpenNonConformities(string $organizationId, int $limit): array
  {
    $id = InspectionOrganizationId::fromString($organizationId);
    $sorting = new Sorting('createdAt', SortDirection::ASC);

    $unresolved = [];
    foreach ([NonConformityStatus::OPEN->value, NonConformityStatus::IN_PROGRESS->value] as $status) {
      foreach ($this->nonConformityRepository->findByOrganizationId($id, status: $status, sorting: $sorting, limit: $limit) as $nonConformity) {
        $unresolved[] = $nonConformity;
      }
    }

    usort(
      $unresolved,
      static fn (NonConformity $left, NonConformity $right): int => $left->createdAt() <=> $right->createdAt(),
    );

    return array_map(
      static fn (NonConformity $nonConformity): OpenNonConformitySummary => new OpenNonConformitySummary(
        id: (string) $nonConformity->id(),
        inspectionId: (string) $nonConformity->inspectionId(),
        description: $nonConformity->description(),
        severity: $nonConformity->severity()->value,
        status: $nonConformity->status()->value,
        dueAt: $nonConformity->dueAt(),
        createdAt: $nonConformity->createdAt(),
      ),
      array_slice($unresolved, 0, max(1, $limit)),
    );
  }

  /**
   * Method countOpenCriticalNonConformities
   *
   * Counts critical non-conformities that are still open or in progress.
   *
   * @access public
   *
   * @param string $organizationId the organization whose inspection or finding metrics are requested
   * @param ?string $status the optional lifecycle status filter
   *
   * @return int
   */
  public function countOpenCriticalNonConformities(string $organizationId, ?string $status = null): int
  {
    return $this->nonConformityRepository->countOpenCriticalByOrganizationId(
      InspectionOrganizationId::fromString($organizationId),
      $status,
    );
  }
}
