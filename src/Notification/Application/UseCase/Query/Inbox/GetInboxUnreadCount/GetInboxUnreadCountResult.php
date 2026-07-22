<?php

declare(strict_types=1);

namespace Notification\Application\UseCase\Query\Inbox\GetInboxUnreadCount;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase GetInboxUnreadCountResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetInboxUnreadCountResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param int $unreadCount the summed unread item count across every registered inbox source
   */
  public function __construct(
    public int $unreadCount,
  ) {
  }
  // #endregion
}
