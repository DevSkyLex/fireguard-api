<?php

declare(strict_types=1);

namespace Webhook\Domain\Event\Subscription;

use DateTimeImmutable;

/**
 * Event WebhookSubscriptionDeletedEvent.
 *
 * Raised when an organization webhook subscription is deleted.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class WebhookSubscriptionDeletedEvent
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
   * WebhookSubscriptionDeletedEvent class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization ID
   * @param string $subscriptionId the webhook subscription ID
   * @param ?string $actorUserId the acting user identifier
   */
  public function __construct(
    public string $organizationId,
    public string $subscriptionId,
    public ?string $actorUserId = null,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}
