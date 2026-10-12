<?php

declare(strict_types=1);

namespace Billing\Application\UseCase\Command\ResumeSubscription;

use Billing\Application\Port\Outbound\BillingReconciliationPort;
use Billing\Application\Port\Outbound\Internal\{StripeGatewayPort, SubscriptionRepositoryPort};
use Billing\Domain\Exception\NoActiveSubscriptionException;
use Shared\Application\Message\{CommandHandler, VoidResult};
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyExecutionPort;

/**
 * UseCase ResumeSubscriptionHandler.
 *
 * Clears a scheduled cancellation on the organization's subscription via Stripe,
 * then mirrors the flag on the local projection so the UI reflects it
 * immediately. The reconciling webhook converges to the same state.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ResumeSubscriptionHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the ResumeSubscriptionHandler class.
   *
   * @since 1.0.0
   *
   * @param SubscriptionRepositoryPort $subscriptions the subscription repository
   * @param StripeGatewayPort $stripe the Stripe gateway
   * @param BillingReconciliationPort $reconciliation the transaction manager
   * @param ?HttpIdempotencyExecutionPort $httpExecution the current execution and stable external identity
   */
  public function __construct(
    private SubscriptionRepositoryPort $subscriptions,
    private StripeGatewayPort $stripe,
    private BillingReconciliationPort $reconciliation,
    private ?HttpIdempotencyExecutionPort $httpExecution = null,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Resumes the organization's subscription.
   *
   * @since 1.0.0
   *
   * @param ResumeSubscriptionCommand $command the command payload
   *
   * @return VoidResult the neutral result
   *
   * @throws NoActiveSubscriptionException when the organization has no live subscription
   */
  public function __invoke(ResumeSubscriptionCommand $command): VoidResult
  {
    return $this->reconciliation->synchronized($command->organizationId, fn (): VoidResult => $this->execute($command));
  }

  /**
   * Method execute.
   *
   * Resumes cancellation with Stripe and saves the refreshed local subscription.
   *
   * @access private
   *
   * @param ResumeSubscriptionCommand $command the organization subscription command
   *
   * @return VoidResult the neutral handler result
   *
   * @throws NoActiveSubscriptionException when no Stripe-backed subscription exists
   */
  private function execute(ResumeSubscriptionCommand $command): VoidResult
  {
    $subscription = $this->subscriptions->findByOrganizationId($command->organizationId, refresh: true);
    $stripeSubscriptionId = $subscription?->stripeSubscriptionId();

    if (null === $subscription || null === $stripeSubscriptionId) {
      throw NoActiveSubscriptionException::forOrganization($command->organizationId);
    }

    $this->httpExecution?->externalEffectStarted('stripe.resume');
    $this->stripe->resumeCancellation($stripeSubscriptionId, $this->httpExecution?->providerKey('stripe.resume'));

    $subscription->resumeCancellation();

    $this->subscriptions->save($subscription);

    return new VoidResult();
  }
  // #endregion
}
