<?php

declare(strict_types=1);

namespace Calendar\Application\UseCase\Command\Feed\ReadCalendarFeedIcs;

use Shared\Application\Message\CommandMessage;

/**
 * Fetches one explicitly identified subscription poll and records its usage.
 *
 * @category Command
 */
final readonly class ReadCalendarFeedIcsCommand implements CommandMessage
{
  /**
   * @param string $secret original authorization credential, never the idempotency key
   */
  public function __construct(public string $secret)
  {
  }
}
