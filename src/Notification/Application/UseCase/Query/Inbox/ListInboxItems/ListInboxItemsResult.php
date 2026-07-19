<?php

declare(strict_types=1);

namespace Notification\Application\UseCase\Query\Inbox\ListInboxItems;

use Notification\Application\Contract\Inbox\InboxItem;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase ListInboxItemsResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListInboxItemsResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param list<InboxItem> $items the merged, page-size-bounded items, ordered by `occurredAt` descending
   * @param string|null $nextCursor the `before` value the client should send to fetch the next page, or null when there is no further page
   * @param bool $hasMore true when a further page is likely available
   */
  public function __construct(
    public array $items,
    public ?string $nextCursor,
    public bool $hasMore,
  ) {
  }
  // #endregion
}
