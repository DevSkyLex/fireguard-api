<?php

declare(strict_types=1);

namespace Billing\Application\UseCase\Query\GetOrganizationPaymentMethod;

use Billing\Application\Port\Outbound\{StripeGatewayPort, SubscriptionRepositoryPort};
use Shared\Application\Message\QueryHandler;

/**
 * UseCase GetOrganizationPaymentMethodHandler.
 *
 * Returns the organization's saved Stripe payment method. When the
 * organization has no billing customer yet, or the customer has no saved
 * card, null is returned rather than an error — a free-plan organization
 * legitimately has none.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOrganizationPaymentMethodHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the GetOrganizationPaymentMethodHandler class.
   *
   * @since 1.0.0
   *
   * @param SubscriptionRepositoryPort $subscriptions the subscription repository
   * @param StripeGatewayPort $stripe the Stripe gateway
   */
  public function __construct(
    private SubscriptionRepositoryPort $subscriptions,
    private StripeGatewayPort $stripe,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Reads the organization's saved payment method.
   *
   * @since 1.0.0
   *
   * @param GetOrganizationPaymentMethodQuery $query the query payload
   *
   * @return GetOrganizationPaymentMethodResult the use case result
   */
  public function __invoke(GetOrganizationPaymentMethodQuery $query): GetOrganizationPaymentMethodResult
  {
    $subscription = $this->subscriptions->findByOrganizationId($query->organizationId);

    if (null === $subscription) {
      return new GetOrganizationPaymentMethodResult(paymentMethod: null);
    }

    return new GetOrganizationPaymentMethodResult(
      paymentMethod: $this->stripe->getPaymentMethod($subscription->stripeCustomerId()),
    );
  }
  // #endregion
}
