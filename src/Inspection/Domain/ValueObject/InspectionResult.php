<?php

declare(strict_types=1);

namespace Inspection\Domain\ValueObject;

use function array_column;

/**
 * Enum InspectionResult.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum InspectionResult: string
{
  case PASS = 'pass';
  case FAIL = 'fail';
  case PARTIAL = 'partial';

  // #region Methods
  /**
   * Method values.
   *
   * Returns all supported inspection result values.
   *
   * @since 1.0.0
   *
   * @return list<string> the inspection result values
   */
  public static function values(): array
  {
    return array_column(self::cases(), 'value');
  }

  /**
   * Method isPass.
   *
   * @since 1.0.0
   */
  public function isPass(): bool
  {
    return self::PASS === $this;
  }

  /**
   * Method isFail.
   *
   * @since 1.0.0
   */
  public function isFail(): bool
  {
    return self::FAIL === $this;
  }
  // #endregion
}
