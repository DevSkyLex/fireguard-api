<?php

declare(strict_types=1);

namespace Notification\Application\UseCase\Query\Inbox\GetInboxUnreadCount;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase GetInboxUnreadCountQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetInboxUnreadCountQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the user identifier the count is scoped to
   * @param string|null $organizationId optional organization filter; when omitted, unread items across every organization (and account-level ones) are counted
   */
  public function __construct(
    public string $userId,
    public ?string $organizationId = null,
  ) {
  }
  // #endregion
}
