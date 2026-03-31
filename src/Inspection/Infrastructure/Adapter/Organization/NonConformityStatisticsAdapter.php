<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Adapter\Organization;

use Inspection\Application\Port\Outbound\NonConformityRepositoryPort;
use Inspection\Domain\ValueObject\{InspectionOrganizationId, NonConformitySeverity, NonConformityStatus};
use Organization\Application\Port\Outbound\NonConformityStatisticsPort;

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

  public function countOpenCriticalNonConformities(string $organizationId, ?string $status = null): int
  {
    return $this->nonConformityRepository->countOpenCriticalByOrganizationId(
      InspectionOrganizationId::fromString($organizationId),
      $status,
    );
  }
}
