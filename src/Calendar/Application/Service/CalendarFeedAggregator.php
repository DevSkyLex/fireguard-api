<?php

declare(strict_types=1);

namespace Calendar\Application\Service;

use Calendar\Application\Contract\Feed\CalendarFeedItem;
use Calendar\Application\Port\Outbound\Event\CalendarEventRepositoryPort;
use Calendar\Application\Port\Outbound\Feed\{InspectionCalendarFeedPort, InterventionCalendarFeedPort, MaintenanceCalendarFeedPort};
use Calendar\Domain\Model\Event\CalendarEvent;
use DateTimeImmutable;
use Shared\Application\Port\Outbound\LoggerPort;
use Throwable;

use function array_map;
use function array_push;
use function usort;

/**
 * Service CalendarFeedAggregator.
 *
 * Merges Calendar's own standalone events with the three cross-module read
 * sources ({@see InspectionCalendarFeedPort}, {@see InterventionCalendarFeedPort},
 * {@see MaintenanceCalendarFeedPort}) into a single, chronologically ordered
 * feed. Mirrors {@see \Notification\Application\Service\InboxAggregator}:
 * each source is called defensively, so one failing/slow source degrades to
 * "contributed nothing" instead of failing the whole feed request, and the
 * failure is logged at `error` level so degradation stays visible instead of
 * silent.
 *
 * The mockup's fourth category, "Audit", has no business existence in this
 * backend — see `Calendar\MODULE.md`. Only these four sources ever
 * contribute.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CalendarFeedAggregator
{
  // #region Constants
  /**
   * Constant PER_SOURCE_LIMIT.
   *
   * Hard cap pushed down into every source query, regardless of the
   * requested date range's actual width. Combined with the mandatory,
   * bounded date range enforced by
   * {@see \Calendar\Application\UseCase\Query\Feed\GetCalendarFeed\GetCalendarFeedHandler},
   * this keeps the feed from ever attempting to load "every intervention
   * ever created" for a busy organization.
   *
   * @since 1.0.0
   *
   * @var int
   */
  private const int PER_SOURCE_LIMIT = 500;

  private const string SOURCE_KEY_CALENDAR_EVENT = 'calendar_event';

  private const string SOURCE_KEY_INSPECTION = 'inspection';

  private const string SOURCE_KEY_INTERVENTION = 'intervention';

  private const string SOURCE_KEY_MAINTENANCE = 'maintenance';

  private const string TARGET_TYPE_CALENDAR_EVENT = 'calendar_event';
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param CalendarEventRepositoryPort $events the standalone calendar event repository port
   * @param InspectionCalendarFeedPort $inspections the inspection feed port
   * @param InterventionCalendarFeedPort $interventions the intervention feed port
   * @param MaintenanceCalendarFeedPort $maintenance the maintenance feed port
   * @param LoggerPort $logger the logger port
   */
  public function __construct(
    private CalendarEventRepositoryPort $events,
    private InspectionCalendarFeedPort $inspections,
    private InterventionCalendarFeedPort $interventions,
    private MaintenanceCalendarFeedPort $maintenance,
    private LoggerPort $logger,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method aggregate.
   *
   * Fetches, merges, and sorts every source's contribution for one
   * organization and date range.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param DateTimeImmutable $from the inclusive range lower bound
   * @param DateTimeImmutable $to the inclusive range upper bound
   *
   * @return list<CalendarFeedItem> the merged, chronologically ordered feed
   */
  public function aggregate(string $organizationId, DateTimeImmutable $from, DateTimeImmutable $to): array
  {
    /** @var list<CalendarFeedItem> $items */
    $items = [];

    array_push($items, ...$this->fetchFromSource(
      self::SOURCE_KEY_CALENDAR_EVENT,
      fn (): array => array_map(
        $this->mapStandaloneEvent(...),
        $this->events->listBetween($organizationId, $from, $to, self::PER_SOURCE_LIMIT),
      ),
    ));

    array_push($items, ...$this->fetchFromSource(
      self::SOURCE_KEY_INSPECTION,
      fn (): array => $this->inspections->findBetween($organizationId, $from, $to, self::PER_SOURCE_LIMIT),
    ));

    array_push($items, ...$this->fetchFromSource(
      self::SOURCE_KEY_INTERVENTION,
      fn (): array => $this->interventions->findBetween($organizationId, $from, $to, self::PER_SOURCE_LIMIT),
    ));

    array_push($items, ...$this->fetchFromSource(
      self::SOURCE_KEY_MAINTENANCE,
      fn (): array => $this->maintenance->findBetween($organizationId, $from, $to, self::PER_SOURCE_LIMIT),
    ));

    usort($items, self::compare(...));

    return $items;
  }

  /**
   * Method fetchFromSource.
   *
   * Calls one source defensively: any throwable is caught, logged, and
   * translated into an empty contribution so one failing/slow source never
   * takes down the whole feed request.
   *
   * @since 1.0.0
   *
   * @param string $sourceKey the source's key, for logging
   * @param callable(): list<CalendarFeedItem> $fetch the source's fetch call
   *
   * @return list<CalendarFeedItem> the source's items, or an empty list on failure
   */
  private function fetchFromSource(string $sourceKey, callable $fetch): array
  {
    try {
      return $fetch();
    } catch (Throwable $exception) {
      $this->logger->error('Calendar feed source failed; degrading to an empty contribution.', [
        'sourceKey' => $sourceKey,
        'error' => $exception->getMessage(),
      ]);

      return [];
    }
  }

  /**
   * Method mapStandaloneEvent.
   *
   * @since 1.0.0
   *
   * @param CalendarEvent $event the standalone calendar event aggregate
   *
   * @return CalendarFeedItem the mapped feed item
   */
  private function mapStandaloneEvent(CalendarEvent $event): CalendarFeedItem
  {
    return new CalendarFeedItem(
      sourceKey: self::SOURCE_KEY_CALENDAR_EVENT,
      id: (string) $event->id(),
      title: $event->title(),
      description: $event->description(),
      startsAt: $event->startsAt(),
      endsAt: $event->endsAt(),
      allDay: $event->allDay(),
      facilityId: $event->facilityId(),
      status: null,
      targetType: self::TARGET_TYPE_CALENDAR_EVENT,
      targetId: (string) $event->id(),
    );
  }

  /**
   * Method compare.
   *
   * Deterministic ordering: `startsAt` ascending first (earliest first);
   * items with the exact same instant are then ordered by `sourceKey`
   * ascending, then by `id` ascending, so ties never depend on source call
   * order or array insertion order.
   *
   * @since 1.0.0
   *
   * @param CalendarFeedItem $left the left-hand item
   * @param CalendarFeedItem $right the right-hand item
   *
   * @return int negative when `$left` sorts before `$right`, positive when after, zero when equal
   */
  private static function compare(CalendarFeedItem $left, CalendarFeedItem $right): int
  {
    $byStartsAt = $left->startsAt <=> $right->startsAt;
    if (0 !== $byStartsAt) {
      return $byStartsAt;
    }

    $bySourceKey = $left->sourceKey <=> $right->sourceKey;
    if (0 !== $bySourceKey) {
      return $bySourceKey;
    }

    return $left->id <=> $right->id;
  }
  // #endregion
}
