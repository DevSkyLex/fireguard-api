<?php

declare(strict_types=1);

namespace Calendar\Application\UseCase\Query\FeedToken\ResolveCalendarFeedToken;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase ResolveCalendarFeedTokenQuery.
 *
 * Carries the raw secret from the public `.ics` URL. The secret is hashed
 * immediately in the handler and never persisted or logged.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ResolveCalendarFeedTokenQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $secret the raw URL-safe secret from the feed URL
   */
  public function __construct(
    public string $secret,
  ) {
  }
  // #endregion
}
