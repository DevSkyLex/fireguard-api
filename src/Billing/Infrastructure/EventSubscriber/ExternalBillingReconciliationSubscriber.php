<?php

declare(strict_types=1);

namespace Billing\Infrastructure\EventSubscriber;

use Billing\Application\Contract\Event\ExternalBillingReconciliationRequested;
use Billing\Application\UseCase\Command\HandleStripeWebhook\HandleStripeWebhookHandler;
use Shared\Application\Port\Outbound\IdempotentConsumerPort;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscriber ExternalBillingReconciliationSubscriber.
 *
 * Reads current Stripe state without reissuing the checkout, portal or cancellation request.
 * Failed reads remain in the existing outbox retry and failure transport policy.
 *
 * @category Subscriber
 */
final readonly class ExternalBillingReconciliationSubscriber implements EventSubscriberInterface
{
  public function __construct(private IdempotentConsumerPort $receipts, private HandleStripeWebhookHandler $reconciliation)
  {
  }

  /**
   * @return array<class-string, string>
   */
  public static function getSubscribedEvents(): array
  {
    return [ExternalBillingReconciliationRequested::class => 'onRequested'];
  }

  public function onRequested(ExternalBillingReconciliationRequested $event): void
  {
    $this->receipts->consume($event->executionId, 'billing.external_reconciliation.apply', fn () => $this->reconciliation->reconcileOrganization($event->organizationId));
  }
}
