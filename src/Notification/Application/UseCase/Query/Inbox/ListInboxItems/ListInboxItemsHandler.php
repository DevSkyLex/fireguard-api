<?php

declare(strict_types=1);

namespace Notification\Application\UseCase\Query\Inbox\ListInboxItems;

use Notification\Application\Service\InboxAggregator;
use Shared\Application\Message\QueryHandler;

use function count;
use function max;
use function min;

/**
 * UseCase ListInboxItemsHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListInboxItemsHandler implements QueryHandler
{
  private const int MIN_LIMIT = 1;

  private const int MAX_LIMIT = 50;

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
   * Aggregates the unified inbox feed for a user.
   *
   * @since 1.0.0
   *
   * @param ListInboxItemsQuery $query the query payload
   *
   * @return ListInboxItemsResult the use case result
   */
  public function __invoke(ListInboxItemsQuery $query): ListInboxItemsResult
  {
    $limit = min(self::MAX_LIMIT, max(self::MIN_LIMIT, $query->limit));

    $aggregation = $this->inboxAggregator->aggregate(
      userId: $query->userId,
      organizationId: $query->organizationId,
      before: $query->before,
      limit: $limit,
    );

    $lastItem = $aggregation->items[count($aggregation->items) - 1] ?? null;
    $nextCursor = $aggregation->hasMore && null !== $lastItem
      ? $lastItem->occurredAt->format('c')
      : null;

    return new ListInboxItemsResult(
      items: $aggregation->items,
      nextCursor: $nextCursor,
      hasMore: $aggregation->hasMore,
    );
  }
  // #endregion
}
