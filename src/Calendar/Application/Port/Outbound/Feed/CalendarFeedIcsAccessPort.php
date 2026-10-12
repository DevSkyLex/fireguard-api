<?php

declare(strict_types=1);

namespace Calendar\Application\Port\Outbound\Feed;

use Calendar\Application\Contract\Feed\CalendarFeedIcsIdentity;

/**
 * Verifies the subscription credential against fresh owner account, membership and scoped grants.
 *
 * @category Port
 */
interface CalendarFeedIcsAccessPort
{
  /**
   * @param string $secret original URL credential, never a logical execution key
   */
  public function resolve(string $secret): CalendarFeedIcsIdentity;
}
