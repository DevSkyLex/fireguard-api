<?php

declare(strict_types=1);

namespace Webhook\Infrastructure\Adapter\Messenger;

use Symfony\Component\Messenger\MessageBusInterface;
use Webhook\Application\Port\Outbound\WebhookDeliveryQueuePort;
use Webhook\Application\UseCase\Command\Delivery\DeliverWebhook\DeliverWebhookCommand;

/**
 * Adapter MessengerWebhookDeliveryQueueAdapter.
 *
 * Mirrors `Import\Infrastructure\Adapter\Messenger\MessengerImportJobQueueAdapter`:
 * dispatches directly onto the raw Symfony Messenger bus (routed to the
 * dedicated `webhook` transport by `config/packages/messenger.yaml`) rather
 * than through `CommandBusPort`, which expects a `HandledStamp` that an
 * async-routed dispatch never produces.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MessengerWebhookDeliveryQueueAdapter implements WebhookDeliveryQueuePort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param MessageBusInterface $messageBus the message bus value
   */
  public function __construct(
    private MessageBusInterface $messageBus,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method dispatch.
   *
   * @since 1.0.0
   *
   * @param string $deliveryId the webhook delivery identifier
   */
  public function dispatch(string $deliveryId): void
  {
    $this->messageBus->dispatch(new DeliverWebhookCommand($deliveryId));
  }
  // #endregion
}
