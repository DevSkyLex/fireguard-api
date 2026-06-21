<?php

declare(strict_types=1);

namespace Billing\Domain\ValueObject;

/**
 * Enum SubscriptionStatus.
 *
 * Mirrors the lifecycle states of a Stripe subscription. A status either grants
 * access to the paid plan (active, trialing, past_due during the grace window) or
 * not (incomplete, canceled, unpaid, …).
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum SubscriptionStatus: string
{
  case INCOMPLETE = 'incomplete';
  case INCOMPLETE_EXPIRED = 'incomplete_expired';
  case TRIALING = 'trialing';
  case ACTIVE = 'active';
  case PAST_DUE = 'past_due';
  case CANCELED = 'canceled';
  case UNPAID = 'unpaid';
  case PAUSED = 'paused';

  // #region Methods
  /**
   * Method fromStripe.
   *
   * Resolves a Stripe status string to an enum case, defaulting to INCOMPLETE
   * for any unknown value so that webhook handling never fails on new statuses.
   *
   * @since 1.0.0
   *
   * @param string $value the raw Stripe status
   *
   * @return self the matching status
   */
  public static function fromStripe(string $value): self
  {
    return self::tryFrom($value) ?? self::INCOMPLETE;
  }

  /**
   * Method grantsAccess.
   *
   * Returns whether the status should grant the organization its paid plan. A
   * past-due subscription keeps access during Stripe's dunning grace window.
   *
   * @since 1.0.0
   *
   * @return bool true when the paid plan must be applied
   */
  public function grantsAccess(): bool
  {
    return match ($this) {
      self::ACTIVE, self::TRIALING, self::PAST_DUE => true,
      default => false,
    };
  }
  // #endregion
}
