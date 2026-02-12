<?php

declare(strict_types=1);

namespace Notification\Application\UseCase\Query\Notification\ListUserNotifications;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase ListUserNotificationsQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListUserNotificationsQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the user identifier
   * @param bool $onlyUnread whether to list only unread notifications
   * @param int $limit maximum number of notifications
   */
  public function __construct(
    public string $userId,
    public bool $onlyUnread = false,
    public int $limit = 50,
  ) {
  }
  // #endregion
}
