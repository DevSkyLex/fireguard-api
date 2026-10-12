<?php

declare(strict_types=1);

namespace Webhook\Infrastructure\Adapter\Event;

use Psr\Log\LoggerInterface;
use Shared\Application\Port\Outbound\{DurableEventContextPort, UuidGeneratorPort};
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;
use Webhook\Application\Contract\Event\WebhookEventProjection;
use Webhook\Application\Port\Inbound\WebhookEventPublisherPort;
use Webhook\Application\UseCase\Command\Delivery\DispatchWebhookEvent\DispatchWebhookEventCommand;

/**
 * Class WebhookEventPublisherAdapter
 *
 * Enqueues curated owner events using the original durable identity and failure semantics.
 *
 * @category Adapter
 */
final readonly class WebhookEventPublisherAdapter implements WebhookEventPublisherPort
{
  // #region Constructor
  /**
   * Constructor
   *
   * @access public
   *
   * @param MessageBusInterface $messageBus the raw async transport bus
   * @param UuidGeneratorPort $uuidFactory the legacy source identity generator
   * @param LoggerInterface $logger the logger
   * @param DurableEventContextPort $eventContext the durable source context
   */
  public function __construct(
    private MessageBusInterface $messageBus,
    private UuidGeneratorPort $uuidFactory,
    private LoggerInterface $logger,
    private DurableEventContextPort $eventContext,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method publish
   *
   * Durable enqueue failures propagate for retry; legacy synchronous failures remain best-effort.
   *
   * @access public
   *
   * @param WebhookEventProjection $projection the curated owner event
   *
   * @return void
   */
  public function publish(WebhookEventProjection $projection): void
  {
    try {
      $this->messageBus->dispatch(new DispatchWebhookEventCommand(
        organizationId: $projection->organizationId,
        eventType: $projection->type->value,
        eventId: $this->eventContext->eventId() ?? $this->uuidFactory->generate(),
        data: $projection->data,
        occurredAt: $projection->occurredAt,
      ));
    } catch (Throwable $exception) {
      $this->logger->error('Failed to dispatch webhook event.', [
        'event_type' => $projection->type->value,
        'organization_id' => $projection->organizationId,
        'error' => $exception->getMessage(),
      ]);
      if (null !== $this->eventContext->eventId()) {
        throw $exception;
      }
    }
  }
  // #endregion
}
