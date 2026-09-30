<?php

declare(strict_types=1);

namespace Messaging\Domain\Model\Message;

use DateTimeImmutable;

/** Optional pin and thread relationship stored with the message. */
final readonly class RestoredMessageRelations
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Carries optional pin and thread relationships restored for a message.
   *
   * @access public
   *
   * @param ?DateTimeImmutable $pinnedAt timestamp when the message was pinned, if pinned
   * @param ?string $pinnedByMemberId member who pinned the message, if pinned
   * @param ?string $parentMessageId optional root message identifier for a reply
   * @param int $replyCount persisted number of replies to the message
   *
   * @return void
   */
  public function __construct(
    public ?DateTimeImmutable $pinnedAt = null,
    public ?string $pinnedByMemberId = null,
    public ?string $parentMessageId = null,
    public int $replyCount = 0,
  ) {
  }
  // #endregion
}
