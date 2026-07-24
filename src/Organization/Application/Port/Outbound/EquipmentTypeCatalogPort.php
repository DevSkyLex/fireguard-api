<?php

declare(strict_types=1);

namespace Organization\Application\Port\Outbound;

/**
 * Port EquipmentTypeCatalogPort.
 *
 * Exposes the equipment-type catalog to the Organization module so compliance
 * settings can validate and describe periodicity keys without ever depending
 * on the Equipment domain enum.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface EquipmentTypeCatalogPort
{
  /**
   * Returns all supported equipment type values.
   *
   * @return list<string> the equipment type values
   */
  public function values(): array;

  /**
   * Returns all supported equipment types with their human-readable label.
   *
   * @return list<array{value: string, label: string}> the equipment type descriptors
   */
  public function descriptors(): array;
}
