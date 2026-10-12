<?php

declare(strict_types=1);

namespace Calendar\Application\Port\Outbound\Feed;

use DateTimeImmutable;

/**
 * Records hourly subscription usage inside the explicit owning main root transaction.
 *
 * @category Port
 */
interface CalendarFeedIcsUsagePort
{
  /**
   * @param string $tokenId verified subscription record
   * @param DateTimeImmutable $now original poll instant
   */
  public function recordUsage(string $tokenId, DateTimeImmutable $now): void;
}
