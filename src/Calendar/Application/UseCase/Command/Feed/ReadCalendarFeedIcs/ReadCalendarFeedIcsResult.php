<?php

declare(strict_types=1);

namespace Calendar\Application\UseCase\Command\Feed\ReadCalendarFeedIcs;

use Shared\Application\Message\ResultMessage;

/**
 * Original calendar response bytes and transport facts captured before the owning commit.
 *
 * @category Result
 */
final readonly class ReadCalendarFeedIcsResult implements ResultMessage
{
  /**
   * @param string $document original UTF-8 document bytes
   * @param int $statusCode original success or temporary incompleteness status
   * @param string $contentType original response media type
   * @param string $cacheControl original private cache policy
   * @param ?string $retryAfter original temporary retry interval
   */
  public function __construct(public string $document, public int $statusCode = 200, public string $contentType = 'text/calendar; charset=utf-8', public string $cacheControl = 'private, max-age=300', public ?string $retryAfter = null)
  {
  }
}
