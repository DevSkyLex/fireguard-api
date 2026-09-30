<?php

declare(strict_types=1);

namespace Inspection\Domain\ValueObject;

use function array_column;

/**
 * Enum NonConformitySeverity.
 *
 * @category ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum NonConformitySeverity: string
{
  /**
   * Case LOW
   */
  case LOW = 'low';

  /**
   * Case MEDIUM
   */
  case MEDIUM = 'medium';

  /**
   * Case HIGH
   */
  case HIGH = 'high';

  /**
   * Case CRITICAL
   */
  case CRITICAL = 'critical';

  // #region Methods
  /**
   * Method values.
   *
   * Returns all supported severity values.
   *
   * @since 1.0.0
   *
   * @return list<string> the severity values
   */
  public static function values(): array
  {
    return array_column(self::cases(), 'value');
  }
  // #endregion
}
