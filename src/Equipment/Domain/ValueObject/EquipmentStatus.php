<?php

declare(strict_types=1);

namespace Equipment\Domain\ValueObject;

/**
 * Enum EquipmentStatus.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum EquipmentStatus: string
{
  case IN_STOCK = 'in_stock';
  case OPERATIONAL = 'operational';
  case UNDER_MAINTENANCE = 'under_maintenance';
  case DECOMMISSIONED = 'decommissioned';

  // #region Methods
  /**
   * Method isOperational.
   *
   * @since 1.0.0
   */
  public function isOperational(): bool
  {
    return self::OPERATIONAL === $this;
  }

  /**
   * Method isDecommissioned.
   *
   * @since 1.0.0
   */
  public function isDecommissioned(): bool
  {
    return self::DECOMMISSIONED === $this;
  }
  // #endregion
}
