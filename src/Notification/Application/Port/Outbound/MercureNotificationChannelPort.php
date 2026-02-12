<?php

declare(strict_types=1);

namespace Notification\Application\Port\Outbound;

use Notification\Domain\Model\Notification\Notification;

/**
 * Port MercureNotificationChannelPort.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface MercureNotificationChannelPort
{
  // #region Methods
  /**
   * Method publish.
   *
   * Publishes notification on Mercure.
   *
   * @since 1.0.0
   *
   * @param Notification $notification the notification to publish
   * @param array<string, mixed> $channelPayload channel-specific payload (not persisted)
   */
  public function publish(Notification $notification, array $channelPayload = []): void;
  // #endregion
}
