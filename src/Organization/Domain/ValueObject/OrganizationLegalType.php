<?php

declare(strict_types=1);

namespace Organization\Domain\ValueObject;

/**
 * Enum OrganizationLegalType.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum OrganizationLegalType: string
{
  case COMPANY = 'company';
  case NON_PROFIT = 'non_profit';
  case PUBLIC_SECTOR = 'public_sector';
  case INDIVIDUAL = 'individual';
  case OTHER = 'other';

  // #region Methods
  /**
   * Method values.
   *
   * Returns all supported legal type values.
   *
   * @since 1.0.0
   *
   * @return list<string> the legal type values
   */
  public static function values(): array
  {
    return [
      self::COMPANY->value,
      self::NON_PROFIT->value,
      self::PUBLIC_SECTOR->value,
      self::INDIVIDUAL->value,
      self::OTHER->value,
    ];
  }

  /**
   * Method requiresRegistrationNumber.
   *
   * Returns whether a registration number is mandatory for this legal type.
   *
   * @since 1.0.0
   *
   * @return bool true when registration number is required
   */
  public function requiresRegistrationNumber(): bool
  {
    return match ($this) {
      self::COMPANY, self::NON_PROFIT => true,
      self::PUBLIC_SECTOR, self::INDIVIDUAL, self::OTHER => false,
    };
  }
  // #endregion
}
