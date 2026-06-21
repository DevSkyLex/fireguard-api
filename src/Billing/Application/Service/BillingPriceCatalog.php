<?php

declare(strict_types=1);

namespace Billing\Application\Service;

use Billing\Domain\ValueObject\BillingInterval;

use function is_array;
use function is_int;
use function is_string;

/**
 * Service BillingPriceCatalog.
 *
 * Maps the stable plan keys and billing cadences to their Stripe price ids and
 * display amounts, sourced from environment-driven configuration so test and
 * live keys differ without code changes. Also provides the reverse lookup
 * (price id => plan key + interval) used when reconciling Stripe webhooks.
 *
 * Configuration shape (per plan key):
 *   { month: { priceId: string, amount: int }, year: { priceId: string, amount: int } }
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class BillingPriceCatalog
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the BillingPriceCatalog class.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $prices the plan-key keyed price configuration
   * @param string $currency the ISO currency code used for display
   */
  public function __construct(
    private array $prices,
    private string $currency,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method priceIdFor.
   *
   * Returns the configured Stripe price id for a plan key and cadence.
   *
   * @since 1.0.0
   *
   * @param string $planKey the plan key
   * @param BillingInterval $interval the billing cadence
   *
   * @return ?string the price id, or null when not configured
   */
  public function priceIdFor(string $planKey, BillingInterval $interval): ?string
  {
    $plan = $this->prices[$planKey] ?? null;
    $entry = is_array($plan) ? ($plan[$interval->value] ?? null) : null;

    if (is_array($entry) && is_string($entry['priceId'] ?? null) && '' !== $entry['priceId']) {
      return $entry['priceId'];
    }

    return null;
  }

  /**
   * Method resolve.
   *
   * Reverse-resolves a Stripe price id to its plan key and cadence.
   *
   * @since 1.0.0
   *
   * @param string $priceId the Stripe price id
   *
   * @return ?array{planKey: string, interval: BillingInterval} the resolved mapping, or null
   */
  public function resolve(string $priceId): ?array
  {
    foreach ($this->prices as $planKey => $intervals) {
      if (!is_array($intervals)) {
        continue;
      }

      foreach ($intervals as $intervalValue => $entry) {
        if (!is_array($entry) || ($entry['priceId'] ?? null) !== $priceId) {
          continue;
        }

        $interval = is_string($intervalValue) ? BillingInterval::fromString($intervalValue) : null;

        if (null !== $interval) {
          return ['planKey' => $planKey, 'interval' => $interval];
        }
      }
    }

    return null;
  }

  /**
   * Method isPayable.
   *
   * Returns whether a plan key has at least one configured Stripe price, i.e. it
   * is a paid plan that must be subscribed to through Stripe.
   *
   * @since 1.0.0
   *
   * @param string $planKey the plan key
   *
   * @return bool true when the plan is payable
   */
  public function isPayable(string $planKey): bool
  {
    return null !== $this->priceIdFor($planKey, BillingInterval::MONTH)
      || null !== $this->priceIdFor($planKey, BillingInterval::YEAR);
  }

  /**
   * Method pricing.
   *
   * Returns the display pricing of every configured payable plan.
   *
   * @since 1.0.0
   *
   * @return list<PlanPricing> the pricing list
   */
  public function pricing(): array
  {
    $pricing = [];

    foreach ($this->prices as $planKey => $intervals) {
      if (!$this->isPayable($planKey)) {
        continue;
      }

      $pricing[] = new PlanPricing(
        planKey: $planKey,
        currency: $this->currency,
        monthlyAmount: $this->amountFor($planKey, BillingInterval::MONTH),
        yearlyAmount: $this->amountFor($planKey, BillingInterval::YEAR),
      );
    }

    return $pricing;
  }

  /**
   * Method currency.
   *
   * @since 1.0.0
   *
   * @return string the ISO currency code used for display
   */
  public function currency(): string
  {
    return $this->currency;
  }

  /**
   * Method amountFor.
   *
   * Returns the display amount for a plan key and cadence.
   *
   * @since 1.0.0
   *
   * @param string $planKey the plan key
   * @param BillingInterval $interval the billing cadence
   *
   * @return ?int the amount in the smallest currency unit, or null
   */
  private function amountFor(string $planKey, BillingInterval $interval): ?int
  {
    $plan = $this->prices[$planKey] ?? null;
    $entry = is_array($plan) ? ($plan[$interval->value] ?? null) : null;
    $amount = is_array($entry) ? ($entry['amount'] ?? null) : null;

    return is_int($amount) ? $amount : null;
  }
  // #endregion
}
