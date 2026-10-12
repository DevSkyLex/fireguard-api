<?php

declare(strict_types=1);

namespace Calendar\Application\Port\Outbound\Feed;

use Calendar\Application\Contract\Feed\CalendarFeedItem;
use DateTimeImmutable;

/**
 * Produces the original bounded RFC 5545 bytes before the owning usage and receipt commit.
 *
 * @category Port
 */
interface CalendarFeedDocumentWriterPort
{
  /**
   * @param list<CalendarFeedItem> $items authorized feed entries
   * @param string $organizationId current organization scope
   * @param DateTimeImmutable $generatedAt original document generation time
   * @param string $timezone current organization civil timezone
   */
  public function write(array $items, string $organizationId, DateTimeImmutable $generatedAt, string $timezone = 'UTC'): string;
}
