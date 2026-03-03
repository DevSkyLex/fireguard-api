<?php

declare(strict_types=1);

namespace Inspection\Domain\ValueObject;

use function array_column;

/**
 * Enum InspectorType.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum InspectorType: string
{
  case USER = 'user';
  case EXTERNAL = 'external';

  // #region Methods
  /**
   * Method values.
   *
   * Returns all supported inspector type values.
   *
   * @since 1.0.0
   *
   * @return list<string> the inspector type values
   */
  public static function values(): array
  {
    return array_column(self::cases(), 'value');
  }
  // #endregion
}
