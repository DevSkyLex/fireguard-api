<?php

declare(strict_types=1);

namespace Maintenance\Domain\ValueObject;

use function array_column;

/**
 * Enum MaintenanceOperationKind
 *
 * Separates inspection completion from maintenance success.
 *
 * @category ValueObject
 */
enum MaintenanceOperationKind: string
{
  /** Case CONTROL */
  case CONTROL = 'control';

  /** Case MAINTENANCE */
  case MAINTENANCE = 'maintenance';

  // #region Methods
  /**
   * Method values
   *
   * Returns the operation kinds accepted by maintenance plans.
   *
   * @access public
   *
   * @return list<string> the stable operation codes
   */
  public static function values(): array
  {
    return array_column(self::cases(), 'value');
  }

  /**
   * Method completesOccurrence
   *
   * A validated control is completed even when it identifies a defect.
   *
   * @access public
   *
   * @param bool $successful whether the executed operation succeeded
   *
   * @return bool whether its occurrence can be completed
   */
  public function completesOccurrence(bool $successful): bool
  {
    return self::CONTROL === $this || $successful;
  }
  // #endregion
}
