<?php

declare(strict_types=1);

namespace Messaging\Application\Contract\Message;

use DateTimeImmutable;

/**
 * Contract MessageView.
 *
 * Read model for a persisted message. The body is always the persisted
 * value (retained even when tombstoned); redaction for a deleted message
 * happens at the Presentation layer (`MessageOutputFactory`), never here.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MessageView
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $id the message identifier
   * @param string $conversationId the owning conversation identifier
   * @param string $organizationId the owning organization identifier
   * @param string $authorMemberId the author's organization member identifier
   * @param string $body the persisted body
   * @param list<string> $mentions the mentioned member identifiers
   * @param ?DateTimeImmutable $editedAt the last edit date, if any
   * @param ?DateTimeImmutable $deletedAt the tombstone date, if any
   * @param ?string $deletedByMemberId the moderator/author who tombstoned the message, if any
   * @param DateTimeImmutable $createdAt the creation date
   * @param DateTimeImmutable $updatedAt the last update date
   * @param ?DateTimeImmutable $pinnedAt the pin date, if any — a property of the CONVERSATION, visible to everyone who can read it
   * @param ?string $pinnedByMemberId the pinning member's identifier, if any
   * @param ?string $parentMessageId the parent message's identifier, when this is a threaded reply (L2.5) — null for a root message
   * @param int $replyCount the number of threaded replies to THIS message (meaningful when this message is itself a root/parent); deliberately NOT redacted when the message is tombstoned, mirroring `pinnedAt`/`isSaved` — the reply messages themselves are separate, still-readable content, unlike reactions/attachments
   * @param list<array{type: string, id: string, label: ?string, code: ?string}> $references the message's structured references (B3), empty when none — raw shapes, not `MessageReference` value objects, mirroring `mentions`' plain-scalar contract
   */
  public function __construct(
    public string $id,
    public string $conversationId,
    public string $organizationId,
    public string $authorMemberId,
    public string $body,
    public array $mentions,
    public ?DateTimeImmutable $editedAt,
    public ?DateTimeImmutable $deletedAt,
    public ?string $deletedByMemberId,
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $updatedAt,
    public ?DateTimeImmutable $pinnedAt = null,
    public ?string $pinnedByMemberId = null,
    public ?string $parentMessageId = null,
    public int $replyCount = 0,
    public array $references = [],
  ) {
  }
  // #endregion
}
