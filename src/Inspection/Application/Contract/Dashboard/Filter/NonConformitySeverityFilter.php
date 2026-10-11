<?php

declare(strict_types=1);

namespace Inspection\Application\Contract\Dashboard\Filter;

use function array_column;

/**
 * Enum NonConformitySeverityFilter.
 *
 * Publishes the owner-supported wire values for cross-module dashboard filters.
 * Values and ordering are checked against the internal lifecycle enum.
 *
 * @category Contract
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum NonConformitySeverityFilter: string
{
  // #region Cases
  /**
   * Case LOW.
   */
  case LOW = 'low';

  /**
   * Case MEDIUM.
   */
  case MEDIUM = 'medium';

  /**
   * Case HIGH.
   */
  case HIGH = 'high';

  /**
   * Case CRITICAL.
   */
  case CRITICAL = 'critical';
  // #endregion

  // #region Methods
  /**
   * Method values.
   *
   * Returns the accepted filter values in their stable validation-message order.
   *
   * @access public
   * @since 1.0.0
   *
   * @return list<string> the accepted wire values
   */
  public static function values(): array
  {
    return array_column(self::cases(), 'value');
  }
  // #endregion
}
