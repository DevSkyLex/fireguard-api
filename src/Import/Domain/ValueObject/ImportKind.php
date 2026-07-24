<?php

declare(strict_types=1);

namespace Import\Domain\ValueObject;

use function array_column;

/**
 * Enum ImportKind.
 *
 * The kind of resource a bulk CSV import provisions. Each kind maps to a
 * dedicated row mapper and inbound provisioning port on the owning module
 * (Equipment, Facility) — no other kinds exist today.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum ImportKind: string
{
  case EQUIPMENT = 'equipment';
  case FACILITY = 'facility';

  // #region Methods
  /**
   * Method values.
   *
   * @static
   *
   * Returns all supported import kind values.
   *
   * @since 1.0.0
   *
   * @return list<string> the import kind values
   */
  public static function values(): array
  {
    return array_column(self::cases(), 'value');
  }
  // #endregion
}
