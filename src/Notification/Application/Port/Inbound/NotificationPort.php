<?php

declare(strict_types=1);

namespace Notification\Application\Port\Inbound;

use Notification\Application\Contract\Notification\{SendNotificationRequest, SentNotification};

/**
 * Port NotificationPort.
 *
 * Inbound port used by other modules to dispatch
 * notifications through configured channels.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface NotificationPort
{
  // #region Methods
  /**
   * Method send.
   *
   * Dispatches a notification.
   *
   * @since 1.0.0
   *
   * @param SendNotificationRequest $request the notification request
   *
   * @return SentNotification the dispatched notification result
   */
  public function send(SendNotificationRequest $request): SentNotification;
  // #endregion
}
