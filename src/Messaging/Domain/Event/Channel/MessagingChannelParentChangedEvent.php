<?php

declare(strict_types=1);

namespace Messaging\Domain\Event\Channel;

use DateTimeImmutable;

/**
 * Event MessagingChannelParentChangedEvent.
 *
 * Raised for `PATCH /api/channels/{id}/parent` (L2.6): a channel is nested
 * under another channel, moved to a different parent, or detached
 * (`parentConversationId` null). Mirrors
 * {@see MessagingChannelTeamBindingChangedEvent} byte for byte — a channel's
 * hierarchy placement is a governance action, not per-message activity, so
 * it is audited exactly like a team binding change. Recorded in the audit
 * ledger as `messaging.channel_parent_changed`.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MessagingChannelParentChangedEvent
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
   * @param string $conversationId the channel (conversation) identifier
   * @param ?string $parentConversationId the newly set parent channel identifier, null when detached
   * @param ?string $actorUserId the acting user identifier
   */
  public function __construct(
    public string $organizationId,
    public string $conversationId,
    public ?string $parentConversationId,
    public ?string $actorUserId = null,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}
