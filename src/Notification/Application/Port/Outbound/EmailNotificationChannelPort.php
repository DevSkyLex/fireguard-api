<?php

declare(strict_types=1);

namespace Notification\Application\Port\Outbound;

use Notification\Domain\Model\Notification\Notification;

/**
 * Port EmailNotificationChannelPort.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface EmailNotificationChannelPort
{
  // #region Methods
  /**
   * Method send.
   *
   * Sends notification through email.
   *
   * @since 1.0.0
   *
   * @param Notification $notification the notification to send
   * @param array<string, mixed> $channelPayload channel-specific payload (not persisted)
   */
  public function send(Notification $notification, array $channelPayload = []): void;
  // #endregion
}
