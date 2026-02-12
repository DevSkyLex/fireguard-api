<?php

declare(strict_types=1);

namespace Notification\Application\UseCase\Query\Notification\GetUserNotification;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase GetUserNotificationQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetUserNotificationQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the user identifier
   * @param string $notificationId the notification identifier
   */
  public function __construct(
    public string $userId,
    public string $notificationId,
  ) {
  }
  // #endregion
}
