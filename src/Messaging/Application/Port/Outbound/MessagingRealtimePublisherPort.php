<?php

declare(strict_types=1);

namespace Messaging\Application\Port\Outbound;

/**
 * Port MessagingRealtimePublisherPort.
 *
 * Messaging-owned real-time fan-out (Mercure), deliberately separate from
 * `Notification\Application\Port\Inbound\NotificationPort`: a posted message
 * is not a persisted per-recipient notification, it is a live update on the
 * conversation's own private topic. Implemented by
 * `Messaging\Infrastructure\Adapter\Realtime\MercureMessagingRealtimePublisherAdapter`.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface MessagingRealtimePublisherPort
{
  // #region Methods
  /**
   * Method publishMessage.
   *
   * Publishes an event on the conversation's private Mercure topic
   * (`/organizations/{organizationId}/conversations/{conversationId}`).
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   * @param string $conversationId the conversation identifier
   * @param array<string, mixed> $payload the event payload
   */
  public function publishMessage(string $organizationId, string $conversationId, array $payload): void;
  // #endregion
}
