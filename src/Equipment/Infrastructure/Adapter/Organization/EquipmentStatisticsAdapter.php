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

  public function countEquipment(string $organizationId): int
  {
    return $this->equipmentRepository->countByOrganizationId(
      EquipmentOrganizationId::fromString($organizationId),
    );
  }

  public function countEquipmentByStatus(string $organizationId): array
  {
    $organizationIdVo = EquipmentOrganizationId::fromString($organizationId);
    $counts = [];

    foreach (EquipmentStatus::cases() as $status) {
      $counts[$status->value] = $this->equipmentRepository->countByOrganizationId(
        organizationId: $organizationIdVo,
        status: $status->value,
      );
    }

    return $counts;
  }

  public function countEquipmentByType(string $organizationId): array
  {
    $organizationIdVo = EquipmentOrganizationId::fromString($organizationId);
    $counts = [];

    foreach (EquipmentType::cases() as $type) {
      $counts[$type->value] = $this->equipmentRepository->countByOrganizationId(
        organizationId: $organizationIdVo,
        type: $type->value,
      );
    }

    return $counts;
  }
}
