<?php

declare(strict_types=1);

namespace Messaging\Domain\Event\Channel;

use DateTimeImmutable;

/**
 * Event MessagingChannelParticipantRemovedEvent.
 *
 * Raised ONLY for a MANUAL participant removal through
 * `DELETE /api/channels/{id}/participants/{memberId}` — the event-driven
 * team resync never dispatches this. Recorded in the audit ledger as
 * `messaging.channel_participant_removed`.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MessagingChannelParticipantRemovedEvent
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
   * @param string $memberId the removed member's identifier
   * @param ?string $actorUserId the acting user identifier
   */
  public function __construct(
    public string $organizationId,
    public string $conversationId,
    public string $memberId,
    public ?string $actorUserId = null,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}
