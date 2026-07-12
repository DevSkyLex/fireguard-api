<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Adapter\Organization;

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
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InspectionStatisticsAdapter implements InspectionStatisticsPort
{
  public function __construct(
    private InspectionRepositoryPort $inspectionRepository,
  ) {
  }

  public function countInspections(
    string $organizationId,
    ?string $status = null,
    ?string $result = null,
    ?string $inspectorType = null,
  ): int {
    return $this->inspectionRepository->countByOrganizationId(
      InspectionOrganizationId::fromString($organizationId),
      result: $result,
      status: $status,
      inspectorType: $inspectorType,
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
      status: InspectionStatus::CANCELLED->value,
    );

    return $total - $cancelled;
  }

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

  public function countInspectionsPerformedSince(string $organizationId, string $performedAtFrom): int
  {
    return $this->inspectionRepository->countByOrganizationId(
      organizationId: InspectionOrganizationId::fromString($organizationId),
      performedAtFrom: $performedAtFrom,
    );
  }

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
      result: $result,
      status: $status,
      performedAtFrom: $performedAtFrom,
      performedAtTo: $performedAtTo,
      inspectorType: $inspectorType,
    );
  }

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
