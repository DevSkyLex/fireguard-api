<?php

declare(strict_types=1);

namespace Notification\Application\UseCase\Query\Notification\GetUnreadNotificationsCount;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase GetUnreadNotificationsCountQuery.
 *
 * @category UseCase
 *
 * @version 1.1.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetUnreadNotificationsCountQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.1.0
   *
   * @param string $userId the user identifier
   * @param string|null $organizationId exact organization filter; when omitted, unread notifications across all organizations (and account-level ones) are counted
   */
  public function __construct(
    public string $userId,
    public ?string $organizationId = null,
  ) {
  }
  // #endregion
}
