<?php

declare(strict_types=1);

namespace Notification\Application\UseCase\Query\Notification\GetUnreadNotificationsCount;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase GetUnreadNotificationsCountResult.
 *
 * @category UseCase
 *
 * @version 1.1.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetUnreadNotificationsCountResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.1.0
   *
   * @param int $count the unread notification count
   */
  public function __construct(
    public int $count,
  ) {
  }
  // #endregion
}
