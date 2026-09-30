<?php

declare(strict_types=1);

namespace Billing\Application\UseCase\Command\CancelSubscription;

use Billing\Application\Port\Outbound\BillingReconciliationPort;
use Billing\Application\Port\Outbound\{StripeGatewayPort, SubscriptionRepositoryPort};
use Billing\Domain\Exception\NoActiveSubscriptionException;
use Shared\Application\Message\{CommandHandler, VoidResult};

/**
 * UseCase CancelSubscriptionHandler.
 *
 * Schedules the cancellation of an organization's subscription at period end via
 * Stripe, then mirrors the `cancel_at_period_end` flag on the local projection so
 * the UI reflects it immediately. The reconciling `customer.subscription.updated`
 * webhook converges to the same state.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CancelSubscriptionHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the CancelSubscriptionHandler class.
   *
   * @since 1.0.0
   *
   * @param SubscriptionRepositoryPort $subscriptions the subscription repository
   * @param StripeGatewayPort $stripe the Stripe gateway
   * @param BillingReconciliationPort $reconciliation the transaction manager
   */
  public function __construct(
    private SubscriptionRepositoryPort $subscriptions,
    private StripeGatewayPort $stripe,
    private BillingReconciliationPort $reconciliation,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Schedules cancellation of the organization's subscription.
   *
   * @since 1.0.0
   *
   * @param CancelSubscriptionCommand $command the command payload
   *
   * @return VoidResult the neutral result
   *
   * @throws NoActiveSubscriptionException when the organization has no live subscription
   */
  public function __invoke(CancelSubscriptionCommand $command): VoidResult
  {
    return $this->reconciliation->synchronized($command->organizationId, fn (): VoidResult => $this->execute($command));
  }

  /**
   * Method execute.
   *
   * Schedules cancellation with Stripe and saves the refreshed local subscription.
   *
   * @access private
   *
   * @param CancelSubscriptionCommand $command the organization subscription command
   *
   * @return VoidResult the neutral handler result
   *
   * @throws NoActiveSubscriptionException when no Stripe-backed subscription exists
   */
  private function execute(CancelSubscriptionCommand $command): VoidResult
  {
    $subscription = $this->subscriptions->findByOrganizationId($command->organizationId, refresh: true);
    $stripeSubscriptionId = $subscription?->stripeSubscriptionId();

    if (null === $subscription || null === $stripeSubscriptionId) {
      throw NoActiveSubscriptionException::forOrganization($command->organizationId);
    }

    $this->stripe->scheduleCancellation($stripeSubscriptionId);

    $subscription->scheduleCancellation();

    $this->subscriptions->save($subscription);

    return new VoidResult();
  }
  // #endregion
}
