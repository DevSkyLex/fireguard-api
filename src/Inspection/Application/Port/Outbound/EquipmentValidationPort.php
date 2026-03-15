<?php

declare(strict_types=1);

namespace Inspection\Application\Port\Outbound;

use InvalidArgumentException;

/**
 * Port EquipmentValidationPort.
 *
 * Validates that an equipment exists and belongs to the expected organization.
 * This abstracts the Equipment module from the Inspection module,
 * maintaining proper module isolation in hexagonal architecture.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface EquipmentValidationPort
{
  // #region Methods
  /**
   * Method assertEquipmentExists.
   *
   * Verifies that the given equipment exists and belongs to the specified organization.
   *
   * @since 1.0.0
   *
   * @param string $equipmentId the equipment identifier
   * @param string $organizationId the expected organization identifier
   *
   * @throws InvalidArgumentException when the equipment is not found or belongs to another organization
   */
  public function assertEquipmentExists(string $equipmentId, string $organizationId): void;
  // #endregion
}
