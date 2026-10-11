<?php

declare(strict_types=1);

namespace Webhook\Infrastructure\Adapter\Messenger;

use Doctrine\DBAL\Connection;
use LogicException;
use Symfony\Component\Messenger\{Envelope, MessageBusInterface};
use Symfony\Component\Messenger\Stamp\DelayStamp;
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
    private Connection $connection,
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
  public function dispatch(string $deliveryId, int $generation = 0, ?string $wakeToken = null, int $delayMilliseconds = 0): void
  {
    if (!$this->connection->isTransactionActive()) {
      throw new LogicException('Webhook enqueue requires its owning main transaction.');
    }

    $this->messageBus->dispatch(new Envelope(
      new DeliverWebhookCommand($deliveryId, $generation, $wakeToken),
      $delayMilliseconds > 0 ? [new DelayStamp($delayMilliseconds)] : [],
    ));
  }
  // #endregion
}
