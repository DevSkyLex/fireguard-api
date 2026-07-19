<?php

declare(strict_types=1);

namespace Webhook\Application\UseCase\Command\Delivery\DispatchWebhookEvent;

use DateTimeImmutable;
use Shared\Application\Message\CommandMessage;

/**
 * UseCase DispatchWebhookEventCommand.
 *
 * Dispatched by `WebhookEventSubscriber` (fire-and-forget, via the raw
 * Symfony message bus) whenever a curated internal domain event occurs.
 * Routed to the `webhook` transport (see `config/packages/messenger.yaml`).
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DispatchWebhookEventCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   * @param string $eventType the public event type ({@see \Webhook\Application\Contract\Event\WebhookEventType})
   * @param string $eventId a stable identifier for the source domain event occurrence (dedupe key)
   * @param array<string, mixed> $data the curated, PII-free event data
   * @param DateTimeImmutable $occurredAt when the source event occurred
   */
  public function __construct(
    public string $organizationId,
    public string $eventType,
    public string $eventId,
    public array $data,
    public DateTimeImmutable $occurredAt,
  ) {
  }
  // #endregion
}
