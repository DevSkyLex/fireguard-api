<?php

declare(strict_types=1);

namespace Calendar\Application\UseCase\Query\Feed\GetCalendarFeed;

use Calendar\Application\Contract\Feed\CalendarFeedItem;
use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase GetCalendarFeedResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetCalendarFeedResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param list<CalendarFeedItem> $items the merged, chronologically ordered feed items
   * @param DateTimeImmutable $from the resolved inclusive range lower bound
   * @param DateTimeImmutable $to the resolved inclusive range upper bound
   */
  public function __construct(
    public array $items,
    public DateTimeImmutable $from,
    public DateTimeImmutable $to,
  ) {
  }
  // #endregion
}
