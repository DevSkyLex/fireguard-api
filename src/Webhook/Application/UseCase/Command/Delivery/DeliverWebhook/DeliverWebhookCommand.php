<?php

declare(strict_types=1);

namespace Webhook\Application\UseCase\Command\Delivery\DeliverWebhook;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase DeliverWebhookCommand.
 *
 * Dispatched by `DispatchWebhookEventHandler` (a real curated event) or
 * `PingWebhookSubscriptionHandler` (a test delivery), and re-dispatched by
 * `RedeliverWebhookHandler` for a manual redelivery. Routed to the
 * `webhook` transport (see `config/packages/messenger.yaml`).
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeliverWebhookCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $deliveryId the webhook delivery identifier to attempt
   */
  public function __construct(
    public string $deliveryId,
  ) {
  }
  // #endregion
}
