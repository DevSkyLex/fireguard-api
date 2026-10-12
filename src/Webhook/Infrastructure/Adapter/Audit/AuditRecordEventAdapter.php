<?php

declare(strict_types=1);

namespace Webhook\Infrastructure\Adapter\Audit;

use Audit\Application\Contract\AuditRecordRequest;
use Audit\Application\Port\Inbound\AuditRecordingPort;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Webhook\Application\Contract\Event\Subscription\{WebhookSubscriptionCreatedEvent, WebhookSubscriptionDeletedEvent};

/**
 * Class AuditRecordEventAdapter
 *
 * Projects Webhook-owned event facts through the published Audit recording capability. Audit owns context, privacy and durable ledger delivery.
 *
 * @category Adapter
 */
final readonly class AuditRecordEventAdapter implements EventSubscriberInterface
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Receives the published durable recording seam.
   *
   * @access public
   *
   * @param AuditRecordingPort $audit generic Audit write capability
   *
   * @return void
   */
  public function __construct(private AuditRecordingPort $audit)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method getSubscribedEvents
   *
   * Preserves the existing owner event names and audit action mapping.
   *
   * @access public
   *
   * @return array<string, string> owner event listeners
   */
  public static function getSubscribedEvents(): array
  {
    return [
      'webhook.webhook_subscription_created_event' => 'onWebhookSubscriptionCreated',
      'webhook.webhook_subscription_deleted_event' => 'onWebhookSubscriptionDeleted',
    ];
  }

  /**
   * Method onWebhookSubscriptionCreated.
   *
   * Records the creation of an outbound webhook subscription. Metadata
   * carries the target URL's host only — never the signing secret.
   *
   * @since 1.2.0
   *
   * @param WebhookSubscriptionCreatedEvent $event the domain event
   */
  public function onWebhookSubscriptionCreated(WebhookSubscriptionCreatedEvent $event): void
  {
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'webhook.subscription_created',
      organizationId: $event->organizationId,
      subjectType: 'webhook_subscription',
      subjectId: $event->subscriptionId,
      metadata: [
        'url_host' => $event->urlHost,
        'event_types' => $event->eventTypes,
      ],
      occurredAt: $event->occurredAt,
      actorId: $event->actorUserId,
    ));
  }

  /**
   * Method onWebhookSubscriptionDeleted.
   *
   * Records the deletion of an outbound webhook subscription.
   *
   * @since 1.2.0
   *
   * @param WebhookSubscriptionDeletedEvent $event the domain event
   */
  public function onWebhookSubscriptionDeleted(WebhookSubscriptionDeletedEvent $event): void
  {
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'webhook.subscription_deleted',
      organizationId: $event->organizationId,
      subjectType: 'webhook_subscription',
      subjectId: $event->subscriptionId,
      metadata: [],
      occurredAt: $event->occurredAt,
      actorId: $event->actorUserId,
    ));
  }
  // #endregion
}
