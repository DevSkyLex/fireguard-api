<?php

declare(strict_types=1);

namespace Messaging\Application\Contract\Event\Channel;

use DateTimeImmutable;

/**
 * Event MessagingChannelCreatedEvent.
 *
 * Raised when a named channel is created through `POST /api/channels`.
 * Recorded in the audit ledger as `messaging.channel_created`.
 *
 * @category Event
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MessagingChannelCreatedEvent
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
   * @param string $name the channel display name
   * @param string $createdByMemberId the creating member's identifier
   * @param ?string $actorUserId the acting user identifier
   * @param ?DateTimeImmutable $occurredAt persisted occurrence time, or null for a new event
   */
  public function __construct(
    public string $organizationId,
    public string $conversationId,
    public string $name,
    public string $createdByMemberId,
    public ?string $actorUserId = null,
    ?DateTimeImmutable $occurredAt = null,
  ) {
    $this->occurredAt = $occurredAt ?? new DateTimeImmutable();
  }
  // #endregion
}
