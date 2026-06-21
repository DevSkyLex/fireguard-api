<?php

declare(strict_types=1);

namespace Billing\Application\UseCase\Query\GetOrganizationSubscription;

use Billing\Application\Port\Outbound\SubscriptionRepositoryPort;
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
   */
  public function __construct(
    private SubscriptionRepositoryPort $subscriptions,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Returns the organization's subscription read model.
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

    if (null === $subscription) {
      return new GetOrganizationSubscriptionResult(
        organizationId: $query->organizationId,
        hasSubscription: false,
        active: false,
      );
    }

    return new GetOrganizationSubscriptionResult(
      organizationId: $query->organizationId,
      hasSubscription: true,
      active: $subscription->status()->grantsAccess(),
      status: $subscription->status()->value,
      planKey: $subscription->planKey(),
      interval: $subscription->interval()?->value,
      currentPeriodEnd: $subscription->currentPeriodEnd(),
      cancelAtPeriodEnd: $subscription->cancelAtPeriodEnd(),
    );
  }
  // #endregion
}
