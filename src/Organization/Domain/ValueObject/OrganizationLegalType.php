<?php

declare(strict_types=1);

namespace Organization\Domain\ValueObject;

/**
 * Enum OrganizationLegalType.
 *
 * Country-agnostic legal entity type, used on reports, invoices and
 * compliance documents alongside the rest of the organization's legal
 * profile.
 *
 * @category ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum OrganizationLegalType: string
{
  /**
   * Case SOLE_PROPRIETORSHIP
   */
  case SOLE_PROPRIETORSHIP = 'sole_proprietorship';

  /**
   * Case PARTNERSHIP
   */
  case PARTNERSHIP = 'partnership';

  /**
   * Case LIMITED_LIABILITY_COMPANY
   */
  case LIMITED_LIABILITY_COMPANY = 'limited_liability_company';

  /**
   * Case PUBLIC_LIMITED_COMPANY
   */
  case PUBLIC_LIMITED_COMPANY = 'public_limited_company';

  /**
   * Case NON_PROFIT_ASSOCIATION
   */
  case NON_PROFIT_ASSOCIATION = 'non_profit_association';

  /**
   * Case PUBLIC_ENTITY
   */
  case PUBLIC_ENTITY = 'public_entity';

  /**
   * Case OTHER
   */
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
      self::SOLE_PROPRIETORSHIP->value,
      self::PARTNERSHIP->value,
      self::LIMITED_LIABILITY_COMPANY->value,
      self::PUBLIC_LIMITED_COMPANY->value,
      self::NON_PROFIT_ASSOCIATION->value,
      self::PUBLIC_ENTITY->value,
      self::OTHER->value,
    ];
  }
  // #endregion
}
