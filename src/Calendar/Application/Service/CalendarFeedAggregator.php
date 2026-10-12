<?php

declare(strict_types=1);

namespace Calendar\Application\Service;

use Calendar\Application\Contract\Feed\{CalendarFeedItem, CalendarFeedSourceState};
use DateTimeImmutable;
use Shared\Application\Port\Outbound\LoggerPort;
use Throwable;

use function array_push;
use function array_slice;
use function count;
use function in_array;
use function usort;

/**
 * Service CalendarFeedAggregator.
 *
 * Merges the registered owner-provided read sources into a single, chronologically ordered
 * feed. Only authorized sources are queried. A failed source is logged and
 * marked unavailable; one extra row detects truncation before the public cap.
 * The result exposes completeness so callers cannot mistake a partial feed
 * for a successful empty result.
 *
 * @category Service
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

  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param CalendarFeedSourceRegistry $sources the configured owner-provided source catalog
   * @param LoggerPort $logger the logger port
   */
  public function __construct(
    private CalendarFeedSourceRegistry $sources,
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
   * @param list<string> $allowedSources source keys authorized by the calling use case
   *
   * @return CalendarFeedAggregationResult the merged feed and source completeness
   */
  public function aggregate(string $organizationId, DateTimeImmutable $from, DateTimeImmutable $to, array $allowedSources): CalendarFeedAggregationResult
  {
    /** @var list<CalendarFeedItem> $items */
    $items = [];

    $sources = [];
    foreach ($this->sources->all() as $sourceKey => $source) {
      if (!in_array($sourceKey, $allowedSources, true)) {
        continue;
      }
      $contribution = $this->fetchFromSource($sourceKey, fn (): array => $source->findBetween($organizationId, $from, $to, self::PER_SOURCE_LIMIT + 1));
      $truncated = null !== $contribution && count($contribution) > self::PER_SOURCE_LIMIT;
      $sources[] = new CalendarFeedSourceState($sourceKey, null !== $contribution, $truncated);
      array_push($items, ...array_slice($contribution ?? [], 0, self::PER_SOURCE_LIMIT));
    }

    usort($items, self::compare(...));

    return new CalendarFeedAggregationResult($items, $sources);
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
   * @return list<CalendarFeedItem>|null the source's items, or null on failure
   */
  private function fetchFromSource(string $sourceKey, callable $fetch): ?array
  {
    try {
      return $fetch();
    } catch (Throwable $exception) {
      $this->logger->error('Calendar feed source failed; degrading to an empty contribution.', [
        'sourceKey' => $sourceKey,
        'error' => $exception->getMessage(),
      ]);

      return null;
    }
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
