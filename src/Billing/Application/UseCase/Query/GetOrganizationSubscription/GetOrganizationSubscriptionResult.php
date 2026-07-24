<?php

declare(strict_types=1);

namespace Billing\Application\UseCase\Query\GetOrganizationSubscription;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase GetOrganizationSubscriptionResult.
 *
 * Read model describing an organization's subscription. When the organization has
 * no subscription, {@see self::$hasSubscription} is false and the remaining fields
 * are null/default.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOrganizationSubscriptionResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the GetOrganizationSubscriptionResult class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param bool $hasSubscription whether a subscription record exists
   * @param bool $active whether the subscription currently grants its paid plan
   * @param ?string $status the lifecycle status value
   * @param ?string $planKey the organization's currently assigned plan key (populated even
   *                         without a subscription record, e.g. the catalog default/free plan)
   * @param ?string $planName the plan's display name, resolved from the Organization module
   * @param ?string $interval the billing cadence value
   * @param ?DateTimeImmutable $currentPeriodEnd the end of the current billing period
   * @param bool $cancelAtPeriodEnd whether cancellation is scheduled at period end
   * @param ?string $currency the ISO currency code used for display, when the plan has pricing
   * @param ?int $monthlyAmount the plan's monthly amount in the currency's smallest unit
   * @param ?int $yearlyAmount the plan's yearly amount in the currency's smallest unit
   */
  public function __construct(
    public string $organizationId,
    public bool $hasSubscription,
    public bool $active,
    public ?string $status = null,
    public ?string $planKey = null,
    public ?string $planName = null,
    public ?string $interval = null,
    public ?DateTimeImmutable $currentPeriodEnd = null,
    public bool $cancelAtPeriodEnd = false,
    public ?string $currency = null,
    public ?int $monthlyAmount = null,
    public ?int $yearlyAmount = null,
  ) {
  }
  // #endregion
}
