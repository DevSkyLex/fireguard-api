<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\Adapter\Organization;

use Equipment\Application\Port\Outbound\EquipmentRepositoryPort;
use Equipment\Domain\ValueObject\{EquipmentOrganizationId, EquipmentStatus, EquipmentType};
use Organization\Application\Port\Outbound\EquipmentStatisticsPort;

/**
 * Adapter EquipmentStatisticsAdapter.
 *
 * Implements the Organization module's equipment statistics port
 * using the Equipment module's repository.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class EquipmentStatisticsAdapter implements EquipmentStatisticsPort
{
  public function __construct(
    private EquipmentRepositoryPort $equipmentRepository,
  ) {
  }

  public function countEquipment(string $organizationId, ?string $type = null, ?string $status = null): int
  {
    return $this->equipmentRepository->countByOrganizationId(
      EquipmentOrganizationId::fromString($organizationId),
      type: $type,
      status: $status,
    );
  }

  public function countEquipmentByStatus(string $organizationId): array
  {
    $counts = $this->equipmentRepository->countByStatusForOrganizationId(
      EquipmentOrganizationId::fromString($organizationId),
    );
    $normalizedCounts = [];

    foreach (EquipmentStatus::cases() as $status) {
      $normalizedCounts[$status->value] = $counts[$status->value] ?? 0;
    }

    return $normalizedCounts;
  }

  public function countEquipmentByType(string $organizationId): array
  {
    $counts = $this->equipmentRepository->countByTypeForOrganizationId(
      EquipmentOrganizationId::fromString($organizationId),
    );
    $normalizedCounts = [];

    foreach (EquipmentType::cases() as $type) {
      $normalizedCounts[$type->value] = $counts[$type->value] ?? 0;
    }

    return $normalizedCounts;
  }
}
