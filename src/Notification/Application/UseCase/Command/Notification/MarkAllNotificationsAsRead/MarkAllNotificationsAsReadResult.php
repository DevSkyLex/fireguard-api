<?php

declare(strict_types=1);

namespace Notification\Application\UseCase\Command\Notification\MarkAllNotificationsAsRead;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase MarkAllNotificationsAsReadResult.
 *
 * @category UseCase
 *
 * @version 1.1.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MarkAllNotificationsAsReadResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.1.0
   *
   * @param int $count the number of notifications marked as read by this call
   */
  public function __construct(
    public int $count,
  ) {
  }
  // #endregion
}
