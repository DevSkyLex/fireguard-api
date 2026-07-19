<?php

declare(strict_types=1);

namespace Maintenance\Domain\ValueObject;

use function array_column;

/**
 * Enum MaintenanceDueStatus.
 *
 * The lifecycle state of a maintenance schedule relative to its next due
 * date: `unscheduled` when the equipment type carries no effective
 * periodicity, `up_to_date` while the next inspection is comfortably in the
 * future, `due_soon` inside the organization's reminder window, and
 * `overdue` once the due date has passed (including equipment that has
 * never been inspected while a periodicity applies).
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum MaintenanceDueStatus: string
{
  case UNSCHEDULED = 'unscheduled';
  case UP_TO_DATE = 'up_to_date';
  case DUE_SOON = 'due_soon';
  case OVERDUE = 'overdue';

  // #region Methods
  /**
   * Method values.
   *
   * Returns all supported due status values.
   *
   * @since 1.0.0
   *
   * @return list<string> the due status values
   */
  public static function values(): array
  {
    return array_column(self::cases(), 'value');
  }
  // #endregion
}
