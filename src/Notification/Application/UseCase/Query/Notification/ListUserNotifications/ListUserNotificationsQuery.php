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
   * @param string|null $type exact type filter (e.g. `organization.invitation`)
   * @param string|null $category category prefix filter (e.g. `organization`)
   */
  public function __construct(
    public string $userId,
    public bool $onlyUnread = false,
    public int $limit = 50,
    public ?string $type = null,
    public ?string $category = null,
  ) {
  }
  // #endregion
}
