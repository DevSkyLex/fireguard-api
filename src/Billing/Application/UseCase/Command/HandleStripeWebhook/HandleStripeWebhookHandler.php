<?php

declare(strict_types=1);

namespace Billing\Application\UseCase\Command\HandleStripeWebhook;

use Billing\Application\Port\Outbound\{
  OrganizationPlanAssignmentPort,
  StripeGatewayPort,
  SubscriptionRepositoryPort
};
use Billing\Application\Port\Outbound\Stripe\StripeEvent;
use Billing\Application\Service\BillingPriceCatalog;
use Billing\Domain\Model\Subscription\Subscription;
use Billing\Domain\ValueObject\{SubscriptionId, SubscriptionStatus};
use DateTimeImmutable;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Message\{CommandHandler, VoidResult};
use Shared\Application\Port\Outbound\TransactionManagerPort;

/**
 * UseCase HandleStripeWebhookHandler.
 *
 * Verifies and reconciles Stripe subscription webhooks. The webhook is the source
 * of truth: it upserts the local subscription projection and applies the matching
 * plan to the organization (or downgrades it to the free plan on cancellation).
 * The flow is idempotent — replaying an event converges to the same state.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class HandleStripeWebhookHandler implements CommandHandler
{
  // #region Constants
  /**
   * The free plan key applied when a subscription ends or lapses.
   *
   * @since 1.0.0
   */
  private const string FREE_PLAN_KEY = 'free';
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the HandleStripeWebhookHandler class.
   *
   * @since 1.0.0
   *
   * @param StripeGatewayPort $stripe the Stripe gateway
   * @param SubscriptionRepositoryPort $subscriptions the subscription repository
   * @param BillingPriceCatalog $priceCatalog the plan price catalog
   * @param OrganizationPlanAssignmentPort $planAssignment the organization plan assignment port
   * @param UuidFactory $uuidFactory the UUID factory
   * @param TransactionManagerPort $transactionManager the transaction manager
   */
  public function __construct(
    private StripeGatewayPort $stripe,
    private SubscriptionRepositoryPort $subscriptions,
    private BillingPriceCatalog $priceCatalog,
    private OrganizationPlanAssignmentPort $planAssignment,
    private UuidFactory $uuidFactory,
    private TransactionManagerPort $transactionManager,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Verifies the signature and reconciles the relevant subscription events.
   *
   * @since 1.0.0
   *
   * @param HandleStripeWebhookCommand $command the command payload
   *
   * @return VoidResult the neutral result
   */
  public function __invoke(HandleStripeWebhookCommand $command): VoidResult
  {
    $event = $this->stripe->parseEvent($command->payload, $command->signature);

    match ($event->type) {
      'customer.subscription.created',
      'customer.subscription.updated' => $this->applyUpsert($event),
      'customer.subscription.deleted' => $this->applyCancellation($event),
      default => null,
    };

    return new VoidResult();
  }

  /**
   * Method applyUpsert.
   *
   * Synchronizes the local subscription and applies the matching plan when an
   * active subscription event is received.
   *
   * @since 1.0.0
   *
   * @param StripeEvent $event the normalized event
   */
  private function applyUpsert(StripeEvent $event): void
  {
    $organizationId = $this->resolveOrganizationId($event);

    if (null === $organizationId || null === $event->customerId || null === $event->subscriptionId) {
      return;
    }

    $mapping = null !== $event->priceId ? $this->priceCatalog->resolve($event->priceId) : null;

    if (null === $mapping) {
      return;
    }

    $status = SubscriptionStatus::fromStripe((string) $event->status);

    $subscription = $this->subscriptions->findByOrganizationId($organizationId)
      ?? $this->startSubscription($organizationId, $event->customerId);

    $subscription->syncFromStripe(
      stripeSubscriptionId: $event->subscriptionId,
      status: $status,
      planKey: $mapping['planKey'],
      interval: $mapping['interval'],
      currentPeriodEnd: $this->toDateTime($event->currentPeriodEnd),
      cancelAtPeriodEnd: $event->cancelAtPeriodEnd,
    );

    $this->transactionManager->transactional(function () use ($subscription): void {
      $this->subscriptions->save($subscription);
    });

    $this->planAssignment->assignPlanByKey(
      $organizationId,
      $status->grantsAccess() ? $mapping['planKey'] : self::FREE_PLAN_KEY,
    );
  }

  /**
   * Method applyCancellation.
   *
   * Marks the subscription canceled and downgrades the organization to the free
   * plan.
   *
   * @since 1.0.0
   *
   * @param StripeEvent $event the normalized event
   */
  private function applyCancellation(StripeEvent $event): void
  {
    $organizationId = $this->resolveOrganizationId($event);

    if (null === $organizationId) {
      return;
    }

    $subscription = $this->subscriptions->findByOrganizationId($organizationId);

    if (null !== $subscription) {
      $subscription->markCanceled();

      $this->transactionManager->transactional(function () use ($subscription): void {
        $this->subscriptions->save($subscription);
      });
    }

    $this->planAssignment->assignPlanByKey($organizationId, self::FREE_PLAN_KEY);
  }

  /**
   * Method resolveOrganizationId.
   *
   * Resolves the organization from the event metadata, falling back to the
   * customer-to-organization mapping stored locally.
   *
   * @since 1.0.0
   *
   * @param StripeEvent $event the normalized event
   *
   * @return ?string the organization identifier, or null when unresolved
   */
  private function resolveOrganizationId(StripeEvent $event): ?string
  {
    if (null !== $event->organizationId) {
      return $event->organizationId;
    }

    if (null !== $event->customerId) {
      return $this->subscriptions->findByStripeCustomerId($event->customerId)?->organizationId();
    }

    return null;
  }

  /**
   * Method startSubscription.
   *
   * Creates a fresh subscription projection linked to the Stripe customer.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $customerId the Stripe customer identifier
   *
   * @return Subscription the created subscription aggregate
   */
  private function startSubscription(string $organizationId, string $customerId): Subscription
  {
    /** @var SubscriptionId $subscriptionId */
    $subscriptionId = $this->uuidFactory->create(SubscriptionId::class);

    return Subscription::start($subscriptionId, $organizationId, $customerId);
  }

  /**
   * Method toDateTime.
   *
   * Converts an optional UNIX timestamp to an immutable date-time.
   *
   * @since 1.0.0
   *
   * @param ?int $timestamp the UNIX timestamp, or null
   *
   * @return ?DateTimeImmutable the converted date-time, or null
   */
  private function toDateTime(?int $timestamp): ?DateTimeImmutable
  {
    return null !== $timestamp ? new DateTimeImmutable()->setTimestamp($timestamp) : null;
  }
  // #endregion
}
