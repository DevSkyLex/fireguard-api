<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\Adapter\Organization;

use Equipment\Domain\ValueObject\EquipmentType;
use Organization\Application\Port\Outbound\EquipmentTypeCatalogPort;

use function array_map;

/**
 * Adapter EquipmentTypeCatalogAdapter.
 *
 * Implements the Organization module's equipment-type catalog port on top of
 * the Equipment domain enum, keeping the enum itself private to this module.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class EquipmentTypeCatalogAdapter implements EquipmentTypeCatalogPort
{
  // #region Methods
  /**
   * Method values.
   *
   * Returns all supported equipment type values.
   *
   * @since 1.0.0
   *
   * @return list<string> the equipment type values
   */
  public function values(): array
  {
    return EquipmentType::values();
  }

  /**
   * Method descriptors.
   *
   * Returns all supported equipment types with their human-readable label.
   *
   * @since 1.0.0
   *
   * @return list<array{value: string, label: string}> the equipment type descriptors
   */
  public function descriptors(): array
  {
    return array_map(
      static fn (EquipmentType $type): array => ['value' => $type->value, 'label' => $type->label()],
      EquipmentType::cases(),
    );
  }
  // #endregion
}
