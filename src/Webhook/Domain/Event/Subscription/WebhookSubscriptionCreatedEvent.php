<?php

declare(strict_types=1);

namespace Webhook\Domain\Event\Subscription;

use DateTimeImmutable;

/**
 * Event WebhookSubscriptionCreatedEvent.
 *
 * Raised when an organization webhook subscription is created. Carries the
 * target URL's host only — never the signing secret — since this event
 * feeds the audit ledger (see `Audit\Infrastructure\EventSubscriber\AuditEventSubscriber`).
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class WebhookSubscriptionCreatedEvent
{
  // #region Properties
  /**
   * Property occurredAt.
   */
  public DateTimeImmutable $occurredAt;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * WebhookSubscriptionCreatedEvent class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization ID
   * @param string $subscriptionId the webhook subscription ID
   * @param string $urlHost the target URL's host component only
   * @param list<string> $eventTypes the subscribed public event type allowlist
   * @param ?string $actorUserId the acting user identifier
   */
  public function __construct(
    public string $organizationId,
    public string $subscriptionId,
    public string $urlHost,
    public array $eventTypes,
    public ?string $actorUserId = null,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}
