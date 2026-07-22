<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Message\BackfillMessageLinks;

use Shared\Application\Message\CommandMessage;

/** UseCase BackfillMessageLinksCommand. */
final readonly class BackfillMessageLinksCommand implements CommandMessage
{
  /**
   * @param ?string $afterMessageId exclusive message-id cursor
   * @param int $batchSize requested batch size, clamped by the handler
   * @param bool $dryRun when true, extracts and counts without persisting
   */
  public function __construct(
    public ?string $afterMessageId,
    public int $batchSize,
    public bool $dryRun = false,
  ) {
  }
}
