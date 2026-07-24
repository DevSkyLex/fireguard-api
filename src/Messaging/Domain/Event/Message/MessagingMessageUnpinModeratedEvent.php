<?php

declare(strict_types=1);

namespace Messaging\Domain\Event\Message;

use DateTimeImmutable;

/**
 * Event MessagingMessageUnpinModeratedEvent.
 *
 * Raised when a manager (holding `organization.messaging.manage`) unpins a
 * message pinned by a DIFFERENT member through
 * `DELETE /api/messages/{id}/pin`. A member unpinning their OWN pin is NOT a
 * moderation action and does not raise this event — mirrors
 * `MessagingMessageModeratedEvent`'s self-vs-moderation split for deletes.
 * Recorded in the audit ledger as `messaging.message_unpin_moderated`.
 *
 * @category Event
 *
 * @version 1.1.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MessagingMessageUnpinModeratedEvent
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
   * @since 1.1.0
   *
   * @param string $organizationId the organization identifier
   * @param string $conversationId the conversation identifier
   * @param string $messageId the unpinned message identifier
   * @param string $pinnedByMemberId the original pinning member's identifier
   * @param ?string $actorUserId the acting (moderating) user identifier
   */
  public function __construct(
    public string $organizationId,
    public string $conversationId,
    public string $messageId,
    public string $pinnedByMemberId,
    public ?string $actorUserId = null,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}
