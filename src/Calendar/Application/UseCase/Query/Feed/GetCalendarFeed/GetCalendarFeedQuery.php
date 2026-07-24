<?php

declare(strict_types=1);

namespace Calendar\Application\UseCase\Query\Feed\GetCalendarFeed;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase GetCalendarFeedQuery.
 *
 * `from`/`to` are raw ISO-8601 strings (with an explicit timezone offset):
 * parsing and range validation is business logic and stays in
 * {@see GetCalendarFeedHandler}, mirroring
 * `Organization\Application\UseCase\Query\Organization\GetOrganizationDashboard\GetOrganizationDashboardQuery`.
 * Both bounds are mandatory here — unlike the dashboard's optional period,
 * the calendar feed never defaults to "everything up to now".
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetCalendarFeedQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the authenticated user identifier
   * @param string $organizationId the owning organization identifier
   * @param string $from the inclusive range lower bound, as an ISO-8601 datetime
   * @param string $to the inclusive range upper bound, as an ISO-8601 datetime
   */
  public function __construct(
    public string $userId,
    public string $organizationId,
    public string $from,
    public string $to,
  ) {
  }
  // #endregion
}
