<?php

declare(strict_types=1);

namespace Calendar\Infrastructure\Adapter\Ical;

use Calendar\Application\Port\Outbound\Feed\CalendarFeedDocumentWriterPort;
use Calendar\Application\Service\CalendarFeedIcalDocumentBuilder;
use DateTimeImmutable;

/**
 * Binds the calendar document builder to the configured public frontend link base.
 *
 * @category Adapter
 */
final readonly class CalendarFeedDocumentWriterAdapter implements CalendarFeedDocumentWriterPort
{
  /**
   * @param string $frontendUrl configured public frontend base URL
   */
  public function __construct(private string $frontendUrl)
  {
  }

  /**
   * {@inheritDoc}
   */
  public function write(array $items, string $organizationId, DateTimeImmutable $generatedAt, string $timezone = 'UTC'): string
  {
    return new CalendarFeedIcalDocumentBuilder($this->frontendUrl)->write($items, $organizationId, $generatedAt, $timezone);
  }
}
