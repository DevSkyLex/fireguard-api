<?php

declare(strict_types=1);

namespace Notification\Application\Service;

use Notification\Application\Contract\Inbox\InboxItem;

/**
 * Service InboxAggregationResult.
 *
 * The outcome of {@see InboxAggregator::aggregate()}: the merged, sorted,
 * page-size-bounded items plus whether at least one more page is likely
 * available.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InboxAggregationResult
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param list<InboxItem> $items the merged items, truncated to the requested page size
   * @param bool $hasMore true when the merge produced more items than the page size before truncation, or when any single source returned a full page (and may therefore hold more beyond what was fetched)
   */
  public function __construct(
    public array $items,
    public bool $hasMore,
  ) {
  }
  // #endregion
}
