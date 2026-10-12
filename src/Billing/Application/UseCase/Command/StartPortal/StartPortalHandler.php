<?php

declare(strict_types=1);

namespace Billing\Application\UseCase\Command\StartPortal;

use Billing\Application\Port\Outbound\BillingReturnUrlPort;
use Billing\Application\Port\Outbound\Internal\{StripeGatewayPort, SubscriptionRepositoryPort};
use Billing\Domain\Exception\BillingCustomerNotFoundException;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyExecutionPort;

/**
 * UseCase StartPortalHandler.
 *
 * Opens a hosted Billing Portal session for the organization's Stripe customer.
 *
 * @category UseCase
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
   * @param BillingReturnUrlPort $returnUrls the server-owned hosted-payment return links
   * @param ?HttpIdempotencyExecutionPort $httpExecution the current execution and stable external identity
   */
  public function __construct(
    private SubscriptionRepositoryPort $subscriptions,
    private StripeGatewayPort $stripe,
    private BillingReturnUrlPort $returnUrls,
    private ?HttpIdempotencyExecutionPort $httpExecution = null,
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
   * @return StartPortalResult the use case result
   *
   * @throws BillingCustomerNotFoundException when the organization has no Stripe customer
   */
  public function __invoke(StartPortalCommand $command): StartPortalResult
  {
    $subscription = $this->subscriptions->findByOrganizationId($command->organizationId);

    if (null === $subscription) {
      throw BillingCustomerNotFoundException::forOrganization($command->organizationId);
    }

    $this->httpExecution?->externalEffectStarted('stripe.portal');
    $url = $this->stripe->createBillingPortalSession(
      customerId: $subscription->stripeCustomerId(),
      returnUrl: $this->returnUrls->portal($command->organizationId),
      idempotencyKey: $this->httpExecution?->providerKey('stripe.portal'),
    );

    return new StartPortalResult(url: $url);
  }
  // #endregion
}
