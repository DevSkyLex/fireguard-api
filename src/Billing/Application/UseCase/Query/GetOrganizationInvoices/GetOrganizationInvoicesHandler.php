<?php

declare(strict_types=1);

namespace Billing\Application\UseCase\Query\GetOrganizationInvoices;

use Billing\Application\Port\Outbound\{StripeGatewayPort, SubscriptionRepositoryPort};
use Shared\Application\Message\QueryHandler;

/**
 * UseCase GetOrganizationInvoicesHandler.
 *
 * Returns the organization's recent Stripe invoices. When the organization has
 * no billing customer yet, an empty list is returned rather than an error.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOrganizationInvoicesHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the GetOrganizationInvoicesHandler class.
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
   * Lists the organization's recent invoices.
   *
   * @since 1.0.0
   *
   * @param GetOrganizationInvoicesQuery $query the query payload
   *
   * @return GetOrganizationInvoicesResult the use case result
   */
  public function __invoke(GetOrganizationInvoicesQuery $query): GetOrganizationInvoicesResult
  {
    $subscription = $this->subscriptions->findByOrganizationId($query->organizationId);

    if (null === $subscription) {
      return new GetOrganizationInvoicesResult(invoices: []);
    }

    return new GetOrganizationInvoicesResult(
      invoices: $this->stripe->listInvoices($subscription->stripeCustomerId()),
    );
  }
  // #endregion
}
