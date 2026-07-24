<?php

declare(strict_types=1);

namespace Calendar\Application\Port\Outbound\Feed;

use Calendar\Application\Contract\Feed\CalendarFeedItem;
use DateTimeImmutable;

/**
 * Port MaintenanceCalendarFeedPort.
 *
 * Cross-module outbound port toward Maintenance, implemented by
 * `Maintenance\Infrastructure\Adapter\Calendar\MaintenanceCalendarFeedAdapter`.
 * Mirrors {@see InspectionCalendarFeedPort}.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface MaintenanceCalendarFeedPort
{
  // #region Methods
  /**
   * Method findBetween.
   *
   * Returns preventive-maintenance schedules whose next due date falls
   * within the given inclusive date range, ordered by `nextDueAt` ascending.
   * Implementations must never load a whole organization's history: `$limit`
   * is a hard cap pushed down into the query, not a post-fetch truncation.
   * Implementations must never throw for expected "nothing to return" cases
   * (return an empty array instead); {@see \Calendar\Application\Service\CalendarFeedAggregator}
   * still wraps every call defensively so an unexpected failure degrades to
   * "this source contributed nothing" rather than failing the whole feed
   * request, but a well-behaved provider should not rely on that safety net.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param DateTimeImmutable $from the inclusive range lower bound
   * @param DateTimeImmutable $to the inclusive range upper bound
   * @param int $limit the maximum number of items to return
   *
   * @return list<CalendarFeedItem> the matching feed items
   */
  public function findBetween(string $organizationId, DateTimeImmutable $from, DateTimeImmutable $to, int $limit): array;
  // #endregion
}
