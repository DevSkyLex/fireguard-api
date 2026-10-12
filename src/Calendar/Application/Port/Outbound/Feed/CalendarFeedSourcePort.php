<?php

declare(strict_types=1);

namespace Calendar\Application\Port\Outbound\Feed;

use Calendar\Application\Contract\Feed\CalendarFeedItem;
use DateTimeImmutable;

/**
 * Interface CalendarFeedSourcePort
 *
 * Publishes one owner's bounded, organization-scoped calendar projection.
 * Sources contribute through the calendar.feed_source service tag without exposing owner internals.
 *
 * @category Port
 */
interface CalendarFeedSourcePort
{
  // #region Methods
  /**
   * Method sourceKey
   *
   * Returns the stable source key carried by every item from this contributor.
   * Keys must be unique within the configured catalog.
   *
   * @access public
   *
   * @return string the source key used for authorization, completeness and item identity
   */
  public function sourceKey(): string;

  /**
   * Method findBetween
   *
   * Returns matching items in the inclusive range, ordered by occurrence time then identifier.
   * Implementations scope reads to the organization and push the hard limit into the query.
   * Empty results return an empty list; unexpected failures propagate to the aggregator.
   *
   * @access public
   *
   * @param string $organizationId the organization whose items may be returned
   * @param DateTimeImmutable $from the inclusive lower bound
   * @param DateTimeImmutable $to the inclusive upper bound
   * @param int $limit the maximum number of items to load
   *
   * @return list<CalendarFeedItem> the matching source items
   */
  public function findBetween(string $organizationId, DateTimeImmutable $from, DateTimeImmutable $to, int $limit): array;
  // #endregion
}
