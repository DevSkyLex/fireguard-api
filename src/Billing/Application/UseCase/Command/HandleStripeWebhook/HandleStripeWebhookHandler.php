<?php

declare(strict_types=1);

namespace Billing\Application\UseCase\Command\HandleStripeWebhook;

use Billing\Application\Contract\Stripe\{StripeEvent, StripeSubscription};
use Billing\Application\Exception\StripeSubscriptionReconciliationException;
use Billing\Application\Port\Outbound\{BillingReconciliationPort, OrganizationPlanAssignmentPort, StripeGatewayPort, SubscriptionRepositoryPort};
use Billing\Application\Service\BillingPriceCatalog;
use Billing\Domain\Model\Subscription\Subscription;
use Billing\Domain\ValueObject\{SubscriptionId, SubscriptionStatus};
use DateTimeImmutable;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Message\{CommandHandler, VoidResult};
use Shared\Application\Port\Outbound\LoggerPort;

use function in_array;
use function strcmp;
use function usort;

/**
 * Reconciles current Stripe state, never the possibly stale webhook snapshot.
 *
 * @category UseCase
 *
 * @version 2.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class HandleStripeWebhookHandler implements CommandHandler
{
  public function __construct(
    private StripeGatewayPort $stripe,
    private SubscriptionRepositoryPort $subscriptions,
    private BillingPriceCatalog $priceCatalog,
    private OrganizationPlanAssignmentPort $planAssignment,
    private UuidFactory $uuidFactory,
    private BillingReconciliationPort $reconciliation,
    private LoggerPort $logger,
  ) {
  }

  public function __invoke(HandleStripeWebhookCommand $command): VoidResult
  {
    $event = $this->stripe->parseEvent($command->payload, $command->signature);
    if (in_array($event->type, ['customer.subscription.created', 'customer.subscription.updated', 'customer.subscription.deleted'], true)) {
      $this->handleSubscriptionEvent($event);
    }

    return new VoidResult();
  }

  private function handleSubscriptionEvent(StripeEvent $event): void
  {
    if ($event->liveMode !== $this->stripe->isLiveMode()) {
      $this->logger->warning('Stripe webhook environment mismatch; event ignored.', ['event_id' => $event->eventId]);

      return;
    }
    if ('' === $event->eventId || null === $event->customerId || null === $event->subscriptionId) {
      $this->logger->warning('Incomplete Stripe subscription event; event ignored.', ['event_id' => $event->eventId]);

      return;
    }

    $organizationId = $this->resolveOrganizationId($event);
    if (null === $organizationId) {
      return;
    }

    $this->reconciliation->processEvent($organizationId, $event, function () use ($organizationId, $event): void {
      // A Checkout or another delivery may have established the mapping while
      // this request waited. Recheck both directions after acquiring the lock.
      $subscription = $this->subscriptions->findByOrganizationId($organizationId, refresh: true);
      if ($organizationId !== $this->resolveOrganizationId($event)
        || (null !== $subscription && $subscription->stripeCustomerId() !== $event->customerId)) {
        $this->logger->warning('Stripe webhook customer mapping mismatch; event ignored.', ['event_id' => $event->eventId]);

        return;
      }
      $this->reconcile($organizationId, $event, $subscription);
    });
  }

  private function reconcile(string $organizationId, StripeEvent $event, ?Subscription $subscription): void
  {
    $customerId = $event->customerId ?? throw new StripeSubscriptionReconciliationException('Missing Stripe customer.');
    $current = $this->currentSubscription($organizationId, $customerId, $event->liveMode);
    if (null === $current) {
      // Only a complete remote read can establish absence. A failed read
      // throws and cannot turn a paid plan into free.
      if (null !== $subscription) {
        $subscription->markCanceled();
        $this->subscriptions->save($subscription);
      }
      $this->planAssignment->assignPlanByKey($organizationId, 'free');

      return;
    }

    $mapping = null !== $current->priceId ? $this->priceCatalog->resolve($current->priceId) : null;
    if (null === $mapping) {
      // Allow Stripe to retry after a catalog/configuration repair instead of
      // acknowledging an event whose current state has not been reconciled.
      throw new StripeSubscriptionReconciliationException('The current Stripe subscription price is not configured.');
    }
    $status = SubscriptionStatus::fromStripe($current->status);
    if (null === $subscription) {
      /** @var SubscriptionId $id */
      $id = $this->uuidFactory->create(SubscriptionId::class);
      $subscription = Subscription::start($id, $organizationId, $customerId);
    }
    $subscription->syncFromStripe(
      stripeSubscriptionId: $current->id,
      status: $status,
      planKey: $mapping['planKey'],
      interval: $mapping['interval'],
      currentPeriodEnd: null === $current->currentPeriodEnd ? null : new DateTimeImmutable()->setTimestamp($current->currentPeriodEnd),
      cancelAtPeriodEnd: $current->cancelAtPeriodEnd,
    );
    $this->subscriptions->save($subscription);
    $this->planAssignment->assignPlanByKey($organizationId, $status->grantsAccess() ? $mapping['planKey'] : 'free');
  }

  private function currentSubscription(string $organizationId, string $customerId, bool $liveMode): ?StripeSubscription
  {
    $subscriptions = $this->stripe->listSubscriptions($customerId);
    foreach ($subscriptions as $subscription) {
      if ($subscription->customerId !== $customerId || $subscription->liveMode !== $liveMode
        || (null !== $subscription->organizationId && $subscription->organizationId !== $organizationId)) {
        throw new StripeSubscriptionReconciliationException('The current Stripe subscription does not match the verified scope.');
      }
    }

    // An abandoned Checkout or old cancellation cannot supersede a live
    // subscription. With several live subscriptions, the newest one wins.
    usort($subscriptions, static function (StripeSubscription $left, StripeSubscription $right): int {
      return (int) SubscriptionStatus::fromStripe($right->status)->grantsAccess() <=> (int) SubscriptionStatus::fromStripe($left->status)->grantsAccess()
        ?: $right->created <=> $left->created
        ?: strcmp($right->id, $left->id);
    });

    return $subscriptions[0] ?? null;
  }

  private function resolveOrganizationId(StripeEvent $event): ?string
  {
    $mapped = null !== $event->customerId ? $this->subscriptions->findByStripeCustomerId($event->customerId)?->organizationId() : null;
    if (null !== $mapped && null !== $event->organizationId && $mapped !== $event->organizationId) {
      $this->logger->warning('Stripe webhook organization mismatch; event ignored.', ['event_id' => $event->eventId]);

      return null;
    }

    return $mapped ?? $event->organizationId;
  }
}
