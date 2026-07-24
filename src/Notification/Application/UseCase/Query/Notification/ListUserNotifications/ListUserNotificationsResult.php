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
   * @param list<GetUserNotificationResult> $notifications the notifications in the current page
   * @param int $total the total number of notifications matching the filters, across all pages
   * @param int $limit the page size used to produce this result
   * @param int $offset the zero-based row offset used to produce this result
   */
  public function __construct(
    public array $notifications,
    public int $total = 0,
    public int $limit = 0,
    public int $offset = 0,
  ) {
  }
  // #endregion
}
