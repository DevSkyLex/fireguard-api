<?php

declare(strict_types=1);

namespace Equipment\Application\Contract\Equipment;

use Maintenance\Application\Contract\Plan\MaintenanceEquipmentOperationsDue;

/**
 * Canonical equipment with grouped maintenance deadlines.
 *
 * @category Contract
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CanonicalEquipmentCollectionItem
{
  // #region Constructor
  /**
   * @since 1.0.0
   */
  public function __construct(public CanonicalEquipmentSnapshot $equipment, public ?MaintenanceEquipmentOperationsDue $operationsDue = null, public string $legacyDueStatus = 'unscheduled')
  {
  }
  // #endregion
}
