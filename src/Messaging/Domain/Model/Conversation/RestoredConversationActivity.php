<?php

declare(strict_types=1);

namespace Messaging\Domain\Model\Conversation;

use DateTimeImmutable;
use Messaging\Domain\ValueObject\ConversationVisibility;

/** Persisted visibility, counters and lifecycle timestamps of a conversation. */
final readonly class RestoredConversationActivity
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Carries persisted visibility, activity counters and lifecycle timestamps.
   *
   * @access public
   *
   * @param ConversationVisibility $visibility conversation visibility mode
   * @param ?DateTimeImmutable $lastMessageAt timestamp of the latest message, when present
   * @param int $messagesCount persisted message count
   * @param bool $isArchived whether the conversation is archived
   * @param DateTimeImmutable $createdAt original creation timestamp
   * @param DateTimeImmutable $updatedAt most recent update timestamp
   *
   * @return void
   */
  public function __construct(
    public ConversationVisibility $visibility,
    public ?DateTimeImmutable $lastMessageAt,
    public int $messagesCount,
    public bool $isArchived,
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $updatedAt,
  ) {
  }
  // #endregion
}
