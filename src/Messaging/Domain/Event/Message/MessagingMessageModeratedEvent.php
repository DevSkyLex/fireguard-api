<?php

declare(strict_types=1);

namespace Messaging\Domain\Event\Message;

use DateTimeImmutable;

/**
 * Event MessagingMessageModeratedEvent.
 *
 * Raised when a manager (holding `organization.messaging.manage`) tombstones
 * another member's message through `DELETE /api/messages/{id}`. A
 * self-delete by the message's own author is NOT a moderation action and
 * does not raise this event. Recorded in the audit ledger as
 * `messaging.message_moderated`.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MessagingMessageModeratedEvent
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
   * @param string $messageId the moderated message identifier
   * @param string $authorMemberId the message author's member identifier
   * @param ?string $actorUserId the acting (moderating) user identifier
   */
  public function __construct(
    public string $organizationId,
    public string $conversationId,
    public string $messageId,
    public string $authorMemberId,
    public ?string $actorUserId = null,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}
