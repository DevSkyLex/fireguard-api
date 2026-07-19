<?php

declare(strict_types=1);

namespace Messaging\Application\Contract\Conversation;

use DateTimeImmutable;

/**
 * Contract ConversationView.
 *
 * Read model for a persisted conversation.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ConversationView
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $id the conversation identifier
   * @param string $organizationId the owning organization identifier
   * @param string $subjectType the subject type value
   * @param ?string $subjectId the subject identifier, if any
   * @param string $visibility the visibility value
   * @param ?DateTimeImmutable $lastMessageAt the last message date, if any
   * @param int $messagesCount the persisted message count
   * @param bool $isArchived the archived flag
   * @param DateTimeImmutable $createdAt the creation date
   * @param DateTimeImmutable $updatedAt the last update date
   * @param ?string $name the channel display name, null for a subject thread
   * @param ?string $teamId the bound organization team identifier, if any
   * @param ?string $createdByMemberId the creating member's identifier, null for a subject thread
   */
  public function __construct(
    public string $id,
    public string $organizationId,
    public string $subjectType,
    public ?string $subjectId,
    public string $visibility,
    public ?DateTimeImmutable $lastMessageAt,
    public int $messagesCount,
    public bool $isArchived,
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $updatedAt,
    public ?string $name = null,
    public ?string $teamId = null,
    public ?string $createdByMemberId = null,
  ) {
  }
  // #endregion
}
