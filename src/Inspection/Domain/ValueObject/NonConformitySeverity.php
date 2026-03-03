<?php

declare(strict_types=1);

namespace Inspection\Domain\ValueObject;

use function array_column;

/**
 * Enum NonConformitySeverity.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum NonConformitySeverity: string
{
  case LOW = 'low';
  case MEDIUM = 'medium';
  case HIGH = 'high';
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
