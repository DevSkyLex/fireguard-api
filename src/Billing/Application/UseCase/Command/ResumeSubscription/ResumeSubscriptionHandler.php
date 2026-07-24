<?php

declare(strict_types=1);

namespace Billing\Application\UseCase\Command\ResumeSubscription;

use Billing\Application\Port\Outbound\{StripeGatewayPort, SubscriptionRepositoryPort};
use Billing\Domain\Exception\NoActiveSubscriptionException;
use Shared\Application\Message\{CommandHandler, VoidResult};
use Shared\Application\Port\Outbound\TransactionManagerPort;

/**
 * UseCase ResumeSubscriptionHandler.
 *
 * Clears a scheduled cancellation on the organization's subscription via Stripe,
 * then mirrors the flag on the local projection so the UI reflects it
 * immediately. The reconciling webhook converges to the same state.
 *
 * @category UseCase
 *
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
   * @param TransactionManagerPort $transactionManager the transaction manager
   */
  public function __construct(
    private SubscriptionRepositoryPort $subscriptions,
    private StripeGatewayPort $stripe,
    private TransactionManagerPort $transactionManager,
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
   * @throws NoActiveSubscriptionException when the organization has no live subscription
   *
   * @return VoidResult the neutral result
   */
  public function __invoke(ResumeSubscriptionCommand $command): VoidResult
  {
    $subscription = $this->subscriptions->findByOrganizationId($command->organizationId);
    $stripeSubscriptionId = $subscription?->stripeSubscriptionId();

    if (null === $subscription || null === $stripeSubscriptionId) {
      throw NoActiveSubscriptionException::forOrganization($command->organizationId);
    }

    $this->stripe->resumeCancellation($stripeSubscriptionId);

    $subscription->resumeCancellation();

    $this->transactionManager->transactional(function () use ($subscription): void {
      $this->subscriptions->save($subscription);
    });

    return new VoidResult();
  }
  // #endregion
}
