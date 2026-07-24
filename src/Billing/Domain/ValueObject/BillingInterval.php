<?php

declare(strict_types=1);

namespace Billing\Domain\ValueObject;

/**
 * Enum BillingInterval.
 *
 * The recurring billing cadence of a subscription price. Matches the Stripe
 * price `recurring.interval` values.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum BillingInterval: string
{
  case MONTH = 'month';
  case YEAR = 'year';

  // #region Methods
  /**
   * Method fromString.
   *
   * Resolves a raw interval string to an enum case.
   *
   * @since 1.0.0
   *
   * @param string $value the raw interval
   *
   * @return ?self the matching interval, or null when unknown
   */
  public static function fromString(string $value): ?self
  {
    return self::tryFrom($value);
  }
  // #endregion
}
