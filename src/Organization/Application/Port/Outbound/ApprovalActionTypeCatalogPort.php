<?php

declare(strict_types=1);

namespace Organization\Application\Port\Outbound;

/**
 * Port ApprovalActionTypeCatalogPort.
 *
 * Exposes the Approval module's regulated action-type catalog to the
 * Organization module so the approval policy settings can validate action
 * rule keys without ever depending on the Approval module's Application
 * layer — mirrors {@see EquipmentTypeCatalogPort}.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface ApprovalActionTypeCatalogPort
{
  /**
   * Returns all supported action type values.
   *
   * @return list<string> the action type values
   */
  public function values(): array;

  /**
   * Returns all supported action types with their human-readable label.
   *
   * @return list<array{value: string, label: string}> the action type descriptors
   */
  public function descriptors(): array;
}
