<?php

declare(strict_types=1);

namespace Intervention\Domain\ValueObject;

use function array_column;

/**
 * Enum RecurrenceFrequency.
 *
 * The base cadence of an intervention recurrence, multiplied by the rule's
 * interval count (e.g. `quarterly` with interval `2` runs every two
 * quarters). See {@see RecurrenceRule} for how a step is derived.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum RecurrenceFrequency: string
{
  case WEEKLY = 'weekly';
  case MONTHLY = 'monthly';
  case QUARTERLY = 'quarterly';
  case SEMIANNUAL = 'semiannual';
  case ANNUAL = 'annual';

  // #region Methods
  /**
   * Method values.
   *
   * @since 1.0.0
   *
   * @return list<string> the recurrence frequency values
   */
  public static function values(): array
  {
    return array_column(self::cases(), 'value');
  }
  // #endregion
}
