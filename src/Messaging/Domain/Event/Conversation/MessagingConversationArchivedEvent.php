<?php

declare(strict_types=1);

namespace Messaging\Domain\Event\Conversation;

use DateTimeImmutable;

/**
 * Event MessagingConversationArchivedEvent.
 *
 * Raised when a conversation is archived through
 * `PATCH /api/conversations/{id}`. Recorded in the audit ledger as
 * `messaging.conversation_archived`.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MessagingConversationArchivedEvent
{
  // #region Properties
  /**
   * Property occurredAt.
   */
  public DateTimeImmutable $occurredAt;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $conversationId the conversation identifier
   * @param string $subjectType the conversation subject type
   * @param ?string $subjectId the conversation subject identifier, if any
   * @param ?string $actorUserId the acting user identifier
   */
  public function __construct(
    public string $organizationId,
    public string $conversationId,
    public string $subjectType,
    public ?string $subjectId,
    public ?string $actorUserId = null,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}
