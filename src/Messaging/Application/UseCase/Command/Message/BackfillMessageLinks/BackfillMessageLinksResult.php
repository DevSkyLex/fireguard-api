<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Message\BackfillMessageLinks;

use Shared\Application\Message\ResultMessage;

/** UseCase BackfillMessageLinksResult. */
final readonly class BackfillMessageLinksResult implements ResultMessage
{
  /**
   * @param int $processedMessages messages inspected in this batch
   * @param int $extractedLinks unique links found in this batch
   * @param ?string $nextCursor cursor for the next batch, null when no row was read
   * @param bool $hasMore whether another batch may exist
   */
  public function __construct(
    public int $processedMessages,
    public int $extractedLinks,
    public ?string $nextCursor,
    public bool $hasMore,
  ) {
  }
}
