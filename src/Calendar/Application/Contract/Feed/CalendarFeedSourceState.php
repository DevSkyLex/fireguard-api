<?php

declare(strict_types=1);

namespace Calendar\Application\Contract\Feed;

/** Availability and bounded-read status for one authorized feed source. */
final readonly class CalendarFeedSourceState
{
  public function __construct(
    public string $sourceKey,
    public bool $available,
    public bool $truncated,
  ) {
  }
}
