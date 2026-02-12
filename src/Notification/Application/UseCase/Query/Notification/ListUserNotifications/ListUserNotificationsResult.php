<?php

declare(strict_types=1);

namespace Notification\Application\UseCase\Query\Notification\ListUserNotifications;

use Notification\Application\UseCase\Query\Notification\GetUserNotification\GetUserNotificationResult;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase ListUserNotificationsResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListUserNotificationsResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param list<GetUserNotificationResult> $notifications the notification list
   */
  public function __construct(
    public array $notifications,
  ) {
  }
  // #endregion
}
