<?php

declare(strict_types=1);

namespace Calendar\Application\Service;

use Calendar\Application\Contract\Feed\{CalendarFeedItem, CalendarFeedSourceState};

/** Feed entries and completeness for authorized sources only. */
final readonly class CalendarFeedAggregationResult
{
  /**
   * @param list<CalendarFeedItem> $items merged entries
   * @param list<CalendarFeedSourceState> $sources authorized source states
   */
  public function __construct(public array $items, public array $sources)
  {
  }

  public function isComplete(): bool
  {
    foreach ($this->sources as $source) {
      if (!$source->available || $source->truncated) {
        return false;
      }
    }

    return true;
  }
}
