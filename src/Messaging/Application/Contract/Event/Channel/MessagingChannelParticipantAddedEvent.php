<?php

declare(strict_types=1);

namespace Messaging\Application\Contract\Event\Channel;

use DateTimeImmutable;

/**
 * Event MessagingChannelParticipantAddedEvent.
 *
 * Raised ONLY for a MANUAL participant add through
 * `POST /api/channels/{id}/participants` — the (high-volume,
 * already-audited-at-the-team-level) event-driven team resync never
 * dispatches this. Recorded in the audit ledger as
 * `messaging.channel_participant_added`.
 *
 * @category Event
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MessagingChannelParticipantAddedEvent
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
   * @param string $memberId the added member's identifier
   * @param ?string $actorUserId the acting user identifier
   * @param ?DateTimeImmutable $occurredAt persisted occurrence time, or null for a new event
   */
  public function __construct(
    public string $organizationId,
    public string $conversationId,
    public string $memberId,
    public ?string $actorUserId = null,
    ?DateTimeImmutable $occurredAt = null,
  ) {
    $this->occurredAt = $occurredAt ?? new DateTimeImmutable();
  }
  // #endregion
}
