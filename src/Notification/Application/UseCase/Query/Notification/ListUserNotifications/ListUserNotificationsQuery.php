<?php

declare(strict_types=1);

namespace Notification\Application\UseCase\Query\Notification\ListUserNotifications;

use Shared\Application\Contract\Pagination\Pagination;
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
   * @param Pagination $pagination the requested page (offset/limit)
   * @param string|null $type exact type filter (e.g. `organization.invitation`)
   * @param string|null $category category prefix filter (e.g. `organization`)
   * @param string|null $organizationId exact organization filter; when omitted, notifications across all organizations (and account-level ones) are returned
   */
  public function __construct(
    public string $userId,
    public bool $onlyUnread = false,
    public Pagination $pagination = new Pagination(),
    public ?string $type = null,
    public ?string $category = null,
    public ?string $organizationId = null,
  ) {
  }
  // #endregion
}
