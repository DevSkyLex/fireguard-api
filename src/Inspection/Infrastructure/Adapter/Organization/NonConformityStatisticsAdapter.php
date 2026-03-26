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

  public function countNonConformities(string $organizationId): int
  {
    return $this->nonConformityRepository->countByOrganizationId(
      InspectionOrganizationId::fromString($organizationId),
    );
  }

  public function countNonConformitiesByStatus(string $organizationId): array
  {
    $organizationIdVo = InspectionOrganizationId::fromString($organizationId);
    $counts = [];

    foreach (NonConformityStatus::cases() as $status) {
      $counts[$status->value] = $this->nonConformityRepository->countByOrganizationId(
        organizationId: $organizationIdVo,
        status: $status->value,
      );
    }

    return $counts;
  }

  public function countNonConformitiesBySeverity(string $organizationId): array
  {
    $organizationIdVo = InspectionOrganizationId::fromString($organizationId);
    $counts = [];

    foreach (NonConformitySeverity::cases() as $severity) {
      $counts[$severity->value] = $this->nonConformityRepository->countByOrganizationId(
        organizationId: $organizationIdVo,
        severity: $severity->value,
      );
    }

    return $counts;
  }
}
