<?php

declare(strict_types=1);

namespace Notification\Application\UseCase\Query\Inbox\GetInboxUnreadCount;

use Notification\Application\Service\InboxAggregator;
use Shared\Application\Message\QueryHandler;

/**
 * UseCase GetInboxUnreadCountHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetInboxUnreadCountHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param InboxAggregator $inboxAggregator the inbox aggregator service
   */
  public function __construct(
    private InboxAggregator $inboxAggregator,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param GetInboxUnreadCountQuery $query the query payload
   *
   * @return GetInboxUnreadCountResult the use case result
   */
  public function __invoke(GetInboxUnreadCountQuery $query): GetInboxUnreadCountResult
  {
    $unreadCount = $this->inboxAggregator->countUnread(
      userId: $query->userId,
      organizationId: $query->organizationId,
    );

    return new GetInboxUnreadCountResult(unreadCount: $unreadCount);
  }
  // #endregion
}
