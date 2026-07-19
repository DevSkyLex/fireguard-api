<?php

declare(strict_types=1);

namespace Messaging\Domain\Model\Message;

use DateTimeImmutable;
use Messaging\Domain\Exception\MessagingValidationException;
use Messaging\Domain\Service\MentionExtractor;
use Messaging\Domain\ValueObject\{MessageBody, MessageId};

use function array_diff;
use function array_values;

/**
 * Model Message.
 *
 * A single message in a conversation. Deletion is a tombstone: `deletedAt`
 * (and `deletedByMemberId`) are set but the body is RETAINED in the
 * database — a compliance product decision — while the Presentation layer
 * redacts the body from the API response whenever the message is deleted
 * (see `MessageOutputFactory`).
 *
 * Threaded replies (L2.5): a message with a non-null `parentMessageId` is a
 * reply to another message in the SAME conversation. Threading is
 * single-level by design — a reply to a reply is refused — enforced in
 * {@see \Messaging\Application\UseCase\Command\Message\PostReply\PostReplyHandler}
 * (not here, since it depends on the PARENT's state) via
 * {@see self::isReply()}. `replyCount` mirrors `Conversation::$messagesCount`:
 * populated on reconstitution, but the hot path bumps it through a single
 * atomic `UPDATE` ({@see \Messaging\Application\Port\Outbound\MessagingMessageRepositoryPort::incrementReplyCount()}),
 * never a load-modify-save cycle.
 *
 * @category Model
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class Message
{
  // #region Constructor
  /**
   * @param list<string> $mentions
   */
  private function __construct(
    private readonly MessageId $id,
    private readonly string $conversationId,
    private readonly string $organizationId,
    private readonly string $authorMemberId,
    private string $body,
    private array $mentions,
    private ?DateTimeImmutable $editedAt,
    private ?DateTimeImmutable $deletedAt,
    private ?string $deletedByMemberId,
    private readonly DateTimeImmutable $createdAt,
    private DateTimeImmutable $updatedAt,
    private ?DateTimeImmutable $pinnedAt = null,
    private ?string $pinnedByMemberId = null,
    private readonly ?string $parentMessageId = null,
    private int $replyCount = 0,
  ) {
  }
  // #endregion

  // #region Factories
  /**
   * Method create.
   *
   * @static
   *
   * Creates a new message, validating and trimming the (already sanitized)
   * body and extracting its `@{memberUuid}` mentions.
   *
   * @since 1.0.0
   *
   * @param MessageId $id the message identifier
   * @param string $conversationId the owning conversation identifier
   * @param string $organizationId the owning organization identifier
   * @param string $authorMemberId the author's organization member identifier
   * @param string $rawBody the sanitized raw body
   * @param MentionExtractor $mentionExtractor the mention extractor
   * @param ?string $parentMessageId the parent message's identifier, when
   *                                 this is a threaded reply (L2.5) — null
   *                                 for a root message. Single-level
   *                                 threading (a reply to a reply) and
   *                                 replying to an already-tombstoned parent
   *                                 are both refused upstream, in
   *                                 {@see \Messaging\Application\UseCase\Command\Message\PostReply\PostReplyHandler},
   *                                 since both checks depend on the PARENT's
   *                                 state, not this message's own state.
   *
   * @return self the created message
   */
  public static function create(
    MessageId $id,
    string $conversationId,
    string $organizationId,
    string $authorMemberId,
    string $rawBody,
    MentionExtractor $mentionExtractor,
    ?string $parentMessageId = null,
  ): self {
    $body = MessageBody::fromString($rawBody)->value;
    $now = new DateTimeImmutable();

    return new self(
      id: $id,
      conversationId: $conversationId,
      organizationId: $organizationId,
      authorMemberId: $authorMemberId,
      body: $body,
      mentions: $mentionExtractor->extract($body),
      editedAt: null,
      deletedAt: null,
      deletedByMemberId: null,
      createdAt: $now,
      updatedAt: $now,
      parentMessageId: $parentMessageId,
    );
  }

  /**
   * Method reconstitute.
   *
   * @static
   *
   * Reconstitutes a message aggregate from persisted state.
   *
   * @since 1.0.0
   *
   * @param MessageId $id the message identifier
   * @param string $conversationId the owning conversation identifier
   * @param string $organizationId the owning organization identifier
   * @param string $authorMemberId the author's organization member identifier
   * @param string $body the persisted body (retained even when deleted)
   * @param list<string> $mentions the persisted mentioned member identifiers
   * @param ?DateTimeImmutable $editedAt the last edit date, if any
   * @param ?DateTimeImmutable $deletedAt the tombstone date, if any
   * @param ?string $deletedByMemberId the moderator/author who tombstoned the message, if any
   * @param DateTimeImmutable $createdAt the creation date
   * @param DateTimeImmutable $updatedAt the last update date
   * @param ?DateTimeImmutable $pinnedAt the pin date, if any
   * @param ?string $pinnedByMemberId the pinning member's identifier, if any
   * @param ?string $parentMessageId the parent message's identifier, when this is a threaded reply (L2.5) — null for a root message
   * @param int $replyCount the persisted reply count (this message's OWN thread, when it is a root/parent) — maintained by an atomic UPDATE on the hot path (see `MessagingMessageRepositoryPort::incrementReplyCount()`), never by re-saving this aggregate
   *
   * @return self the reconstituted message
   */
  public static function reconstitute(
    MessageId $id,
    string $conversationId,
    string $organizationId,
    string $authorMemberId,
    string $body,
    array $mentions,
    ?DateTimeImmutable $editedAt,
    ?DateTimeImmutable $deletedAt,
    ?string $deletedByMemberId,
    DateTimeImmutable $createdAt,
    DateTimeImmutable $updatedAt,
    ?DateTimeImmutable $pinnedAt = null,
    ?string $pinnedByMemberId = null,
    ?string $parentMessageId = null,
    int $replyCount = 0,
  ): self {
    return new self($id, $conversationId, $organizationId, $authorMemberId, $body, $mentions, $editedAt, $deletedAt, $deletedByMemberId, $createdAt, $updatedAt, $pinnedAt, $pinnedByMemberId, $parentMessageId, $replyCount);
  }
  // #endregion

  // #region Methods
  /**
   * Method edit.
   *
   * Replaces the message body (re-validating and re-extracting mentions).
   * Author-only enforcement happens upstream in
   * {@see \Messaging\Application\UseCase\Command\Message\EditMessage\EditMessageHandler}
   * since it depends on the acting member, not domain state; this method
   * only refuses to edit an already-tombstoned message.
   *
   * @since 1.0.0
   *
   * @param string $rawBody the new sanitized raw body
   * @param MentionExtractor $mentionExtractor the mention extractor
   *
   * @return list<string> the mentioned member identifiers newly added by this edit
   */
  public function edit(string $rawBody, MentionExtractor $mentionExtractor): array
  {
    if (null !== $this->deletedAt) {
      throw new MessagingValidationException('A deleted message cannot be edited.');
    }

    $body = MessageBody::fromString($rawBody)->value;
    $mentions = $mentionExtractor->extract($body);
    $newlyMentioned = array_values(array_diff($mentions, $this->mentions));

    $this->body = $body;
    $this->mentions = $mentions;
    $this->editedAt = new DateTimeImmutable();
    $this->updatedAt = $this->editedAt;

    return $newlyMentioned;
  }

  /**
   * Method tombstone.
   *
   * Soft-deletes the message: the body is retained (compliance) but marked
   * deleted, to be redacted at the Presentation layer. Idempotent.
   *
   * @since 1.0.0
   *
   * @param string $deletedByMemberId the member identifier performing the deletion
   *
   * @return bool true when the message transitioned to deleted
   */
  public function tombstone(string $deletedByMemberId): bool
  {
    if (null !== $this->deletedAt) {
      return false;
    }

    $this->deletedAt = new DateTimeImmutable();
    $this->deletedByMemberId = $deletedByMemberId;
    $this->updatedAt = $this->deletedAt;

    return true;
  }

  /**
   * Method isDeleted.
   *
   * @since 1.0.0
   */
  public function isDeleted(): bool
  {
    return null !== $this->deletedAt;
  }

  /**
   * Method pin.
   *
   * Pins the message in its conversation — a property of the CONVERSATION,
   * visible to everyone who can read it (unlike a saved message, which is
   * private to one member). Idempotent: pinning an already-pinned message is
   * a no-op that keeps the original `pinnedAt`/`pinnedByMemberId` (re-pinning
   * never "steals" credit for the pin). Refuses to pin an already-tombstoned
   * message — pinning content that has no substance left to highlight would
   * only produce a permanently empty pinned entry.
   *
   * @since 1.1.0
   *
   * @param string $memberId the pinning member's identifier
   *
   * @return bool true when the message transitioned to pinned
   */
  public function pin(string $memberId): bool
  {
    if (null !== $this->pinnedAt) {
      return false;
    }

    if (null !== $this->deletedAt) {
      throw new MessagingValidationException('A deleted message cannot be pinned.');
    }

    $this->pinnedAt = new DateTimeImmutable();
    $this->pinnedByMemberId = $memberId;
    $this->updatedAt = $this->pinnedAt;

    return true;
  }

  /**
   * Method unpin.
   *
   * Unpins the message. Idempotent: unpinning a message that is not
   * currently pinned is a no-op and never throws.
   *
   * @since 1.1.0
   *
   * @return bool true when the message transitioned to unpinned
   */
  public function unpin(): bool
  {
    if (null === $this->pinnedAt) {
      return false;
    }

    $this->pinnedAt = null;
    $this->pinnedByMemberId = null;
    $this->updatedAt = new DateTimeImmutable();

    return true;
  }

  /**
   * Method isPinned.
   *
   * @since 1.1.0
   */
  public function isPinned(): bool
  {
    return null !== $this->pinnedAt;
  }

  /**
   * Method pinnedAt.
   *
   * @since 1.1.0
   */
  public function pinnedAt(): ?DateTimeImmutable
  {
    return $this->pinnedAt;
  }

  /**
   * Method pinnedByMemberId.
   *
   * @since 1.1.0
   */
  public function pinnedByMemberId(): ?string
  {
    return $this->pinnedByMemberId;
  }

  /**
   * Method isReply.
   *
   * True when this message is a threaded reply (L2.5) — i.e. it has a
   * parent message. Used upstream to enforce single-level threading: a
   * reply to an already-`isReply()` message is refused by
   * {@see \Messaging\Application\UseCase\Command\Message\PostReply\PostReplyHandler}
   * rather than allowed to nest further.
   *
   * @since 1.2.0
   */
  public function isReply(): bool
  {
    return null !== $this->parentMessageId;
  }

  /**
   * Method parentMessageId.
   *
   * @since 1.2.0
   *
   * @return ?string the parent message's identifier, null for a root message
   */
  public function parentMessageId(): ?string
  {
    return $this->parentMessageId;
  }

  /**
   * Method replyCount.
   *
   * The persisted number of threaded replies to THIS message (meaningful
   * when this message is itself a root/parent). Reflects the value already
   * loaded from storage; nothing in this aggregate recomputes it.
   *
   * @since 1.2.0
   */
  public function replyCount(): int
  {
    return $this->replyCount;
  }

  /**
   * Method incrementReplyCount.
   *
   * Exists for domain completeness and unit testing, mirroring
   * `Conversation::incrementMessagesCount()` — the hot path of posting a
   * reply increments `reply_count` on the PARENT through a single atomic SQL
   * `UPDATE` ({@see \Messaging\Application\Port\Outbound\MessagingMessageRepositoryPort::incrementReplyCount()}),
   * never a load-modify-save cycle on this aggregate, to stay correct under
   * concurrent replies.
   *
   * @since 1.2.0
   */
  public function incrementReplyCount(): void
  {
    ++$this->replyCount;
  }

  /**
   * Method isAuthoredBy.
   *
   * @since 1.0.0
   *
   * @param string $memberId the member identifier to check
   */
  public function isAuthoredBy(string $memberId): bool
  {
    return $this->authorMemberId === $memberId;
  }

  /**
   * Method id.
   *
   * @since 1.0.0
   */
  public function id(): MessageId
  {
    return $this->id;
  }

  /**
   * Method conversationId.
   *
   * @since 1.0.0
   */
  public function conversationId(): string
  {
    return $this->conversationId;
  }

  /**
   * Method organizationId.
   *
   * @since 1.0.0
   */
  public function organizationId(): string
  {
    return $this->organizationId;
  }

  /**
   * Method authorMemberId.
   *
   * @since 1.0.0
   */
  public function authorMemberId(): string
  {
    return $this->authorMemberId;
  }

  /**
   * Method body.
   *
   * @since 1.0.0
   */
  public function body(): string
  {
    return $this->body;
  }

  /**
   * Method mentions.
   *
   * @since 1.0.0
   *
   * @return list<string>
   */
  public function mentions(): array
  {
    return $this->mentions;
  }

  /**
   * Method editedAt.
   *
   * @since 1.0.0
   */
  public function editedAt(): ?DateTimeImmutable
  {
    return $this->editedAt;
  }

  /**
   * Method deletedAt.
   *
   * @since 1.0.0
   */
  public function deletedAt(): ?DateTimeImmutable
  {
    return $this->deletedAt;
  }

  /**
   * Method deletedByMemberId.
   *
   * @since 1.0.0
   */
  public function deletedByMemberId(): ?string
  {
    return $this->deletedByMemberId;
  }

  /**
   * Method createdAt.
   *
   * @since 1.0.0
   */
  public function createdAt(): DateTimeImmutable
  {
    return $this->createdAt;
  }

  /**
   * Method updatedAt.
   *
   * @since 1.0.0
   */
  public function updatedAt(): DateTimeImmutable
  {
    return $this->updatedAt;
  }
  // #endregion
}
