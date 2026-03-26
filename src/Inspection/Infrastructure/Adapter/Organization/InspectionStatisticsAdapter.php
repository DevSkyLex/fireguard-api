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

  public function countInspections(string $organizationId): int
  {
    return $this->inspectionRepository->countByOrganizationId(
      InspectionOrganizationId::fromString($organizationId),
    );
  }

  public function countInspectionsByStatus(string $organizationId): array
  {
    $organizationIdVo = InspectionOrganizationId::fromString($organizationId);
    $counts = [];

    foreach (InspectionStatus::cases() as $status) {
      $counts[$status->value] = $this->inspectionRepository->countByOrganizationId(
        organizationId: $organizationIdVo,
        status: $status->value,
      );
    }

    return $counts;
  }

  public function countInspectionsByResult(string $organizationId): array
  {
    $organizationIdVo = InspectionOrganizationId::fromString($organizationId);
    $counts = [];

    foreach (InspectionResult::cases() as $result) {
      $counts[$result->value] = $this->inspectionRepository->countByOrganizationId(
        organizationId: $organizationIdVo,
        result: $result->value,
      );
    }

    return $counts;
  }

  public function countInspectionsByInspectorType(string $organizationId): array
  {
    $organizationIdVo = InspectionOrganizationId::fromString($organizationId);
    $counts = [];

    foreach (InspectorType::cases() as $inspectorType) {
      $counts[$inspectorType->value] = $this->inspectionRepository->countByOrganizationId(
        organizationId: $organizationIdVo,
        inspectorType: $inspectorType->value,
      );
    }

    return $counts;
  }

  public function countInspectionsPerformedSince(string $organizationId, string $performedAtFrom): int
  {
    return $this->inspectionRepository->countByOrganizationId(
      organizationId: InspectionOrganizationId::fromString($organizationId),
      performedAtFrom: $performedAtFrom,
    );
  }
}
