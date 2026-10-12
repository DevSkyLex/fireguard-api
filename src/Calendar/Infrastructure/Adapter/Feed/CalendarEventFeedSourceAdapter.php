<?php

declare(strict_types=1);

namespace Calendar\Infrastructure\Adapter\Feed;

use Calendar\Application\Contract\Feed\CalendarFeedItem;
use Calendar\Application\Port\Outbound\Feed\CalendarFeedSourcePort;
use Calendar\Application\Port\Outbound\Internal\Event\CalendarEventRepositoryPort;
use Calendar\Domain\Model\Event\CalendarEvent;
use DateTimeImmutable;

use function array_map;

/**
 * Class CalendarEventFeedSourceAdapter
 *
 * Projects Calendar's standalone events through the same contributor contract as business owners.
 * The repository retains organization scoping, overlap semantics and bounded persistence reads.
 *
 * @category Adapter
 */
final readonly class CalendarEventFeedSourceAdapter implements CalendarFeedSourcePort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param CalendarEventRepositoryPort $events the owner-private standalone event repository
   *
   * @return void
   */
  public function __construct(private CalendarEventRepositoryPort $events)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method sourceKey
   *
   * @access public
   *
   * @return string the standalone event source key
   */
  public function sourceKey(): string
  {
    return 'calendar_event';
  }

  /**
   * Method findBetween
   *
   * Projects the repository's bounded overlap query without changing event content or dates.
   *
   * @access public
   *
   * @param string $organizationId the owning organization identifier
   * @param DateTimeImmutable $from the inclusive lower bound
   * @param DateTimeImmutable $to the inclusive upper bound
   * @param int $limit the maximum number of events to load
   *
   * @return list<CalendarFeedItem> the standalone event items
   */
  public function findBetween(string $organizationId, DateTimeImmutable $from, DateTimeImmutable $to, int $limit): array
  {
    return array_map($this->toFeedItem(...), $this->events->listBetween($organizationId, $from, $to, $limit));
  }

  /**
   * Method toFeedItem
   *
   * @access private
   *
   * @param CalendarEvent $event the standalone event aggregate
   *
   * @return CalendarFeedItem the unchanged event projection
   */
  private function toFeedItem(CalendarEvent $event): CalendarFeedItem
  {
    return new CalendarFeedItem(
      sourceKey: $this->sourceKey(),
      id: (string) $event->id(),
      title: $event->title(),
      description: $event->description(),
      startsAt: $event->startsAt(),
      endsAt: $event->endsAt(),
      allDay: $event->allDay(),
      facilityId: $event->facilityId(),
      status: null,
      targetType: 'calendar_event',
      targetId: (string) $event->id(),
    );
  }
  // #endregion
}
