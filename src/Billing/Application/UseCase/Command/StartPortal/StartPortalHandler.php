<?php

declare(strict_types=1);

namespace Billing\Application\UseCase\Command\StartPortal;

use Billing\Application\Port\Outbound\{StripeGatewayPort, SubscriptionRepositoryPort};
use Billing\Domain\Exception\BillingCustomerNotFoundException;
use Shared\Application\Message\CommandHandler;

use function rtrim;
use function sprintf;

/**
 * UseCase StartPortalHandler.
 *
 * Opens a hosted Billing Portal session for the organization's Stripe customer.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class StartPortalHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the StartPortalHandler class.
   *
   * @since 1.0.0
   *
   * @param SubscriptionRepositoryPort $subscriptions the subscription repository
   * @param StripeGatewayPort $stripe the Stripe gateway
   * @param string $frontendUrl the public frontend base URL for the return link
   */
  public function __construct(
    private SubscriptionRepositoryPort $subscriptions,
    private StripeGatewayPort $stripe,
    private string $frontendUrl,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Opens the Billing Portal session for the organization.
   *
   * @since 1.0.0
   *
   * @param StartPortalCommand $command the command payload
   *
   * @throws BillingCustomerNotFoundException when the organization has no Stripe customer
   *
   * @return StartPortalResult the use case result
   */
  public function __invoke(StartPortalCommand $command): StartPortalResult
  {
    $subscription = $this->subscriptions->findByOrganizationId($command->organizationId);

    if (null === $subscription) {
      throw BillingCustomerNotFoundException::forOrganization($command->organizationId);
    }

    $url = $this->stripe->createBillingPortalSession(
      customerId: $subscription->stripeCustomerId(),
      returnUrl: sprintf(
        '%s/organizations/%s/settings?tab=subscription',
        rtrim($this->frontendUrl, '/'),
        $command->organizationId,
      ),
    );

    return new StartPortalResult(url: $url);
  }
  // #endregion
}
