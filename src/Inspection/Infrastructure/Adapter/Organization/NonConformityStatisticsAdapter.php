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
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class NonConformityStatisticsAdapter implements NonConformityStatisticsPort
{
  public function __construct(
    private NonConformityRepositoryPort $nonConformityRepository,
  ) {
  }

  public function countNonConformities(string $organizationId, ?string $severity = null, ?string $status = null): int
  {
    return $this->nonConformityRepository->countByOrganizationId(
      InspectionOrganizationId::fromString($organizationId),
      severity: $severity,
      status: $status,
    );
  }

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

  public function countSlaBreachedNonConformities(string $organizationId): int
  {
    return $this->nonConformityRepository->countSlaBreachedByOrganizationId(
      InspectionOrganizationId::fromString($organizationId),
    );
  }

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

  public function countOpenCriticalNonConformities(string $organizationId, ?string $status = null): int
  {
    return $this->nonConformityRepository->countOpenCriticalByOrganizationId(
      InspectionOrganizationId::fromString($organizationId),
      $status,
    );
  }
}
