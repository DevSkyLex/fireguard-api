<?php

declare(strict_types=1);

namespace Calendar\Application\UseCase\Query\FeedToken\ResolveCalendarFeedToken;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase ResolveCalendarFeedTokenResult.
 *
 * The resolved member identity plus the iCal window bounds (now minus 30
 * days / now plus 180 days), pre-formatted as ISO-8601 UTC strings so the
 * caller can feed them straight into
 * {@see \Calendar\Application\UseCase\Query\Feed\GetCalendarFeed\GetCalendarFeedQuery}
 * — the window policy stays in the Application layer, not in the controller.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ResolveCalendarFeedTokenResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization the token is scoped to
   * @param string $userId the member the token belongs to
   * @param string $from the inclusive window lower bound (ISO-8601 UTC)
   * @param string $to the inclusive window upper bound (ISO-8601 UTC)
   */
  public function __construct(
    public string $organizationId,
    public string $userId,
    public string $from,
    public string $to,
  ) {
  }
  // #endregion
}
