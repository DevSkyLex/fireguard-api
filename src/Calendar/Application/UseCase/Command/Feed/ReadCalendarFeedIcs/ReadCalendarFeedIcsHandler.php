<?php

declare(strict_types=1);

namespace Calendar\Application\UseCase\Command\Feed\ReadCalendarFeedIcs;

use Calendar\Application\Port\Outbound\Feed\{CalendarFeedDocumentWriterPort, CalendarFeedIcsAccessPort, CalendarFeedIcsUsagePort};
use Calendar\Application\Service\CalendarFeedAggregator;
use DateTimeZone;
use Organization\Application\Port\Inbound\OrganizationWorkforceDirectoryPort;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{ClockPort, RootTransactionManagerPort};

/**
 * Commits subscription usage and the original authorized iCalendar outcome in one main root transaction.
 *
 * @category Handler
 */
final readonly class ReadCalendarFeedIcsHandler implements CommandHandler
{
  /**
   * @param CalendarFeedIcsAccessPort $access fresh subscription and exact visibility gate
   * @param CalendarFeedIcsUsagePort $usage conditional hourly usage persistence
   * @param CalendarFeedAggregator $aggregator bounded owner-provided feed sources
   * @param OrganizationWorkforceDirectoryPort $organizations published organization regional context
   * @param CalendarFeedDocumentWriterPort $writer original RFC 5545 byte serializer
   * @param ClockPort $clock original poll instant
   * @param RootTransactionManagerPort $rootTransaction explicit final main commit
   */
  public function __construct(private CalendarFeedIcsAccessPort $access, private CalendarFeedIcsUsagePort $usage, private CalendarFeedAggregator $aggregator, private OrganizationWorkforceDirectoryPort $organizations, private CalendarFeedDocumentWriterPort $writer, private ClockPort $clock, private RootTransactionManagerPort $rootTransaction)
  {
  }

  /**
   * @param ReadCalendarFeedIcsCommand $command original subscription credential
   */
  public function __invoke(ReadCalendarFeedIcsCommand $command): ReadCalendarFeedIcsResult
  {
    return $this->rootTransaction->transactional(function () use ($command): ReadCalendarFeedIcsResult {
      $identity = $this->access->resolve($command->secret);
      $now = $this->clock->now()->setTimezone(new DateTimeZone('UTC'));
      $this->usage->recordUsage($identity->tokenId, $now);
      $feed = $this->aggregator->aggregate($identity->organizationId, $now->modify('-30 days'), $now->modify('+180 days'), $identity->sources);
      if (!$feed->isComplete()) {
        return new ReadCalendarFeedIcsResult('Calendar feed temporarily incomplete.', 503, 'text/plain; charset=utf-8', 'private, no-store', '300');
      }
      $region = $this->organizations->context($identity->organizationId);

      return new ReadCalendarFeedIcsResult($this->writer->write($feed->items, $identity->organizationId, $now, $region->timezone ?? 'UTC'));
    });
  }
}
