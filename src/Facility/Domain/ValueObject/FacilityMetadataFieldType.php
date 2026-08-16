<?php

declare(strict_types=1);

namespace Facility\Domain\ValueObject;

/**
 * Enum FacilityMetadataFieldType.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum FacilityMetadataFieldType: string
{
  case TEXT = 'text';
  case NUMBER = 'number';
  case DATE = 'date';
  case BOOLEAN = 'boolean';
  case SELECT = 'select';

  // #region Methods
  /**
   * Method values.
   *
   * Returns all supported metadata field type values.
   *
   * @since 1.0.0
   *
   * @return list<string> the metadata field type values
   */
  public static function values(): array
  {
    return [
      self::TEXT->value,
      self::NUMBER->value,
      self::DATE->value,
      self::BOOLEAN->value,
      self::SELECT->value,
    ];
  }
  // #endregion
}
