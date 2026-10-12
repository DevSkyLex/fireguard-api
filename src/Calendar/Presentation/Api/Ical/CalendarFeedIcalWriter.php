<?php

declare(strict_types=1);

namespace Calendar\Presentation\Api\Ical;

use Calendar\Application\Contract\Feed\CalendarFeedItem;
use Calendar\Application\Service\CalendarFeedIcalDocumentBuilder;
use DateTimeImmutable;

/**
 * Retains the presentation serializer contract while sharing the exact RFC 5545 document builder.
 *
 * @category Ical
 */
final readonly class CalendarFeedIcalWriter
{
  /**
   * @param string $frontendUrl configured public frontend base URL
   */
  public function __construct(private string $frontendUrl)
  {
  }

  /**
   * @param list<CalendarFeedItem> $items authorized bounded feed entries
   * @param string $organizationId current feed organization
   * @param DateTimeImmutable $generatedAt original document generation instant
   * @param string $timezone organization civil timezone
   */
  public function write(array $items, string $organizationId, DateTimeImmutable $generatedAt, string $timezone = 'UTC'): string
  {
    return new CalendarFeedIcalDocumentBuilder($this->frontendUrl)->write($items, $organizationId, $generatedAt, $timezone);
  }
}
