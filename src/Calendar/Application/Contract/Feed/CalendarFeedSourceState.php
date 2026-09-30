<?php

declare(strict_types=1);

namespace Calendar\Application\Contract\Feed;

/** Availability and bounded-read status for one authorized feed source. */
final readonly class CalendarFeedSourceState
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Reports whether an authorized calendar feed source responded and whether its bounded result was truncated.
   *
   * @access public
   *
   * @param string $sourceKey stable key identifying the feed source
   * @param bool $available whether the source completed successfully
   * @param bool $truncated whether the source result exceeded its configured bound
   *
   * @return void
   */
  public function __construct(
    public string $sourceKey,
    public bool $available,
    public bool $truncated,
  ) {
  }
  // #endregion
}
