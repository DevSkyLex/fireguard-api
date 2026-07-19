<?php

declare(strict_types=1);

namespace Messaging\Application\Contract\Channel;

use DateTimeImmutable;

/**
 * Contract ChannelView.
 *
 * Read model for a persisted channel (a `Conversation` with
 * `subjectType=CHANNEL`).
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ChannelView
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $id the channel (conversation) identifier
   * @param string $organizationId the owning organization identifier
   * @param string $name the channel display name
   * @param ?string $teamId the bound organization team identifier, if any
   * @param ?string $createdByMemberId the creating member's identifier
   * @param int $participantCount the current participant count
   * @param bool $isArchived the archived flag
   * @param ?DateTimeImmutable $lastMessageAt the last message date, if any
   * @param int $messagesCount the persisted message count
   * @param DateTimeImmutable $createdAt the creation date
   * @param DateTimeImmutable $updatedAt the last update date
   * @param ?string $parentChannelId the parent channel's identifier, if this channel is nested under another (L2.6)
   */
  public function __construct(
    public string $id,
    public string $organizationId,
    public string $name,
    public ?string $teamId,
    public ?string $createdByMemberId,
    public int $participantCount,
    public bool $isArchived,
    public ?DateTimeImmutable $lastMessageAt,
    public int $messagesCount,
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $updatedAt,
    public ?string $parentChannelId = null,
  ) {
  }
  // #endregion
}
