<?php

declare(strict_types=1);

namespace Billing\Application\UseCase\Query\GetOrganizationSubscription;

use Billing\Application\Port\Outbound\{OrganizationPlanPort, SubscriptionRepositoryPort};
use Billing\Application\Service\BillingPriceCatalog;
use Shared\Application\Message\QueryHandler;

/**
 * UseCase GetOrganizationSubscriptionHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOrganizationSubscriptionHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the GetOrganizationSubscriptionHandler class.
   *
   * @since 1.0.0
   *
   * @param SubscriptionRepositoryPort $subscriptions the subscription repository
   * @param OrganizationPlanPort $organizationPlan the organization's currently assigned plan port
   * @param BillingPriceCatalog $priceCatalog the plan price catalog
   */
  public function __construct(
    private SubscriptionRepositoryPort $subscriptions,
    private OrganizationPlanPort $organizationPlan,
    private BillingPriceCatalog $priceCatalog,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Returns the organization's subscription read model. The plan key/name and
   * display pricing are resolved from the organization's CURRENTLY assigned
   * plan (falling back to the catalog default) regardless of whether a local
   * `billing_subscriptions` row exists, so the payload is self-sufficient for
   * the "current plan" card even for organizations that never went through
   * Checkout (e.g. the free plan).
   *
   * @since 1.0.0
   *
   * @param GetOrganizationSubscriptionQuery $query the query payload
   *
   * @return GetOrganizationSubscriptionResult the use case result
   */
  public function __invoke(GetOrganizationSubscriptionQuery $query): GetOrganizationSubscriptionResult
  {
    $subscription = $this->subscriptions->findByOrganizationId($query->organizationId);
    $currentPlan = $this->organizationPlan->findCurrentPlan($query->organizationId);
    $pricing = null !== $currentPlan ? $this->priceCatalog->pricingFor($currentPlan->key) : null;

    if (null === $subscription) {
      return new GetOrganizationSubscriptionResult(
        organizationId: $query->organizationId,
        hasSubscription: false,
        active: false,
        planKey: $currentPlan?->key,
        planName: $currentPlan?->name,
        currency: $pricing?->currency,
        monthlyAmount: $pricing?->monthlyAmount,
        yearlyAmount: $pricing?->yearlyAmount,
      );
    }

    return new GetOrganizationSubscriptionResult(
      organizationId: $query->organizationId,
      hasSubscription: true,
      active: $subscription->status()->grantsAccess(),
      status: $subscription->status()->value,
      // The organization's canonical current plan takes precedence over the
      // subscription's own last-billed plan key: a canceled subscription is
      // downgraded to the free plan by the webhook, and the "current plan"
      // card must reflect that, not the stale billed plan.
      planKey: $currentPlan->key ?? $subscription->planKey(),
      planName: $currentPlan?->name,
      interval: $subscription->interval()?->value,
      currentPeriodEnd: $subscription->currentPeriodEnd(),
      cancelAtPeriodEnd: $subscription->cancelAtPeriodEnd(),
      currency: $pricing?->currency,
      monthlyAmount: $pricing?->monthlyAmount,
      yearlyAmount: $pricing?->yearlyAmount,
    );
  }
  // #endregion
}
