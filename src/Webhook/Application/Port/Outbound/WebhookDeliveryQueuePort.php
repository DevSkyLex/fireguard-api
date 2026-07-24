<?php

declare(strict_types=1);

namespace Webhook\Application\Port\Outbound;

/**
 * Port WebhookDeliveryQueuePort.
 *
 * Fire-and-forget async enqueue, mirroring
 * `Import\Application\Port\Outbound\ImportJobQueuePort`.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface WebhookDeliveryQueuePort
{
  // #region Methods
  /**
   * Method dispatch.
   *
   * Enqueues a single delivery attempt onto the dedicated `webhook`
   * Messenger transport.
   *
   * @since 1.0.0
   *
   * @param string $deliveryId the webhook delivery identifier to attempt
   */
  public function dispatch(string $deliveryId): void;
  // #endregion
}
