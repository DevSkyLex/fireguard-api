<?php

declare(strict_types=1);

namespace Messaging\Domain\Model\Conversation;

use DateTimeImmutable;
use Messaging\Domain\ValueObject\{ChannelName, ConversationId, ConversationVisibility, MessagingSubjectType};

/**
 * Model Conversation.
 *
 * A discussion thread bound one-to-one to a core-entity subject
 * (`organizationId`, `subjectType`, `subjectId`), enforced unique at the
 * persistence layer. `subjectId` is already nullable and `visibility`
 * already carries the `PARTICIPANTS` case so v2 channel conversations are a
 * purely additive schema change (see
 * {@see ConversationVisibility}).
 *
 * A v2 channel is the SAME aggregate with `subjectType=CHANNEL`,
 * `subjectId=null`, `visibility=PARTICIPANTS`, and a non-null `name`
 * (`ChannelName`); `teamId`/`createdByMemberId` are channel-only and stay
 * null for a v1 subject-thread conversation. {@see self::createChannel()}
 * is the dedicated factory; {@see self::create()}/{@see self::reconstitute()}
 * (the v1 subject-thread path) are unchanged for existing call sites.
 *
 * A channel may also be nested under another channel (L2.6,
 * `parentConversationId`, backed by `messaging_conversations.parent_conversation_id`,
 * self-FK `ON DELETE SET NULL`) — set/cleared through {@see self::setParent()}
 * after validation by
 * {@see \Messaging\Application\Service\MessagingChannelHierarchyGuard}. Only
 * meaningful for `subjectType=CHANNEL` conversations; always null for a v1
 * subject thread or a direct conversation.
 *
 * `touchLastMessage()`/`incrementMessagesCount()` exist for domain
 * completeness and unit testing, but the hot path of posting a message
 * updates `messages_count`/`last_message_at` through a single atomic SQL
 * statement ({@see \Messaging\Application\Port\Outbound\MessagingConversationRepositoryPort::touchOnNewMessage()})
 * rather than a load-modify-save cycle, to stay correct under concurrent
 * posts.
 *
 * @category Model
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class Conversation
{
  // #region Constructor
  private function __construct(
    private readonly ConversationId $id,
    private readonly string $organizationId,
    private readonly MessagingSubjectType $subjectType,
    private readonly ?string $subjectId,
    private ConversationVisibility $visibility,
    private ?DateTimeImmutable $lastMessageAt,
    private int $messagesCount,
    private bool $isArchived,
    private readonly DateTimeImmutable $createdAt,
    private DateTimeImmutable $updatedAt,
    private ?ChannelName $name = null,
    private ?string $teamId = null,
    private readonly ?string $createdByMemberId = null,
    private ?string $parentConversationId = null,
  ) {
  }
  // #endregion

  // #region Factories
  /**
   * Method create.
   *
   * @static
   *
   * Creates a new conversation for a subject. Visibility defaults to
   * `SUBJECT`, the only value in use in v1.
   *
   * @since 1.0.0
   *
   * @param ConversationId $id the conversation identifier
   * @param string $organizationId the owning organization identifier
   * @param MessagingSubjectType $subjectType the subject type
   * @param ?string $subjectId the subject identifier
   *
   * @return self the created conversation
   */
  public static function create(
    ConversationId $id,
    string $organizationId,
    MessagingSubjectType $subjectType,
    ?string $subjectId,
  ): self {
    $now = new DateTimeImmutable();

    return new self(
      id: $id,
      organizationId: $organizationId,
      subjectType: $subjectType,
      subjectId: $subjectId,
      visibility: ConversationVisibility::SUBJECT,
      lastMessageAt: null,
      messagesCount: 0,
      isArchived: false,
      createdAt: $now,
      updatedAt: $now,
    );
  }

  /**
   * Method createChannel.
   *
   * @static
   *
   * Creates a new named channel: `subjectType=CHANNEL`, `subjectId=null`,
   * `visibility=PARTICIPANTS`. The creating member is NOT added as a
   * participant by this factory — {@see \Messaging\Application\UseCase\Command\Channel\CreateChannel\CreateChannelHandler}
   * adds the first `messaging_participants` row (and seeds its read marker)
   * after persisting the conversation.
   *
   * @since 1.0.0
   *
   * @param ConversationId $id the conversation identifier
   * @param string $organizationId the owning organization identifier
   * @param ChannelName $name the channel display name
   * @param string $createdByMemberId the creating member's identifier
   *
   * @return self the created channel
   */
  public static function createChannel(
    ConversationId $id,
    string $organizationId,
    ChannelName $name,
    string $createdByMemberId,
  ): self {
    $now = new DateTimeImmutable();

    return new self(
      id: $id,
      organizationId: $organizationId,
      subjectType: MessagingSubjectType::CHANNEL,
      subjectId: null,
      visibility: ConversationVisibility::PARTICIPANTS,
      lastMessageAt: null,
      messagesCount: 0,
      isArchived: false,
      createdAt: $now,
      updatedAt: $now,
      name: $name,
      teamId: null,
      createdByMemberId: $createdByMemberId,
    );
  }

  /**
   * Method reconstitute.
   *
   * @static
   *
   * Reconstitutes a conversation aggregate from persisted state.
   *
   * @since 1.0.0
   *
   * @param ConversationId $id the conversation identifier
   * @param string $organizationId the owning organization identifier
   * @param MessagingSubjectType $subjectType the subject type
   * @param ?string $subjectId the subject identifier
   * @param ConversationVisibility $visibility the visibility value
   * @param ?DateTimeImmutable $lastMessageAt the last message date, if any
   * @param int $messagesCount the persisted message count
   * @param bool $isArchived the archived flag
   * @param DateTimeImmutable $createdAt the creation date
   * @param DateTimeImmutable $updatedAt the last update date
   * @param ?ChannelName $name the channel display name, if this is a channel
   * @param ?string $teamId the bound organization team identifier, if any
   * @param ?string $createdByMemberId the creating member's identifier, if this is a channel
   * @param ?string $parentConversationId the parent channel's identifier, if this channel is nested under another (L2.6)
   *
   * @return self the reconstituted conversation
   */
  public static function reconstitute(
    ConversationId $id,
    string $organizationId,
    MessagingSubjectType $subjectType,
    ?string $subjectId,
    ConversationVisibility $visibility,
    ?DateTimeImmutable $lastMessageAt,
    int $messagesCount,
    bool $isArchived,
    DateTimeImmutable $createdAt,
    DateTimeImmutable $updatedAt,
    ?ChannelName $name = null,
    ?string $teamId = null,
    ?string $createdByMemberId = null,
    ?string $parentConversationId = null,
  ): self {
    return new self($id, $organizationId, $subjectType, $subjectId, $visibility, $lastMessageAt, $messagesCount, $isArchived, $createdAt, $updatedAt, $name, $teamId, $createdByMemberId, $parentConversationId);
  }
  // #endregion

  // #region Methods
  /**
   * Method archive.
   *
   * Archives the conversation. Idempotent: returns false (and leaves the
   * conversation untouched) when it is already archived, so the handler can
   * skip emitting `MessagingConversationArchivedEvent` a second time.
   *
   * @since 1.0.0
   *
   * @return bool true when the conversation transitioned to archived
   */
  public function archive(): bool
  {
    if ($this->isArchived) {
      return false;
    }

    $this->isArchived = true;
    $this->updatedAt = new DateTimeImmutable();

    return true;
  }

  /**
   * Method unarchive.
   *
   * Restores an archived conversation. Idempotent.
   *
   * @since 1.0.0
   *
   * @return bool true when the conversation transitioned to active
   */
  public function unarchive(): bool
  {
    if (!$this->isArchived) {
      return false;
    }

    $this->isArchived = false;
    $this->updatedAt = new DateTimeImmutable();

    return true;
  }

  /**
   * Method touchLastMessage.
   *
   * @since 1.0.0
   *
   * @param DateTimeImmutable $at the new message's creation date
   */
  public function touchLastMessage(DateTimeImmutable $at): void
  {
    $this->lastMessageAt = $at;
    $this->updatedAt = $at;
  }

  /**
   * Method incrementMessagesCount.
   *
   * @since 1.0.0
   */
  public function incrementMessagesCount(): void
  {
    ++$this->messagesCount;
  }

  /**
   * Method rename.
   *
   * Renames a channel. Only meaningful for `subjectType=CHANNEL`
   * conversations (no caller path exists for a v1 subject thread).
   *
   * @since 1.0.0
   *
   * @param ChannelName $name the new channel display name
   */
  public function rename(ChannelName $name): void
  {
    $this->name = $name;
    $this->updatedAt = new DateTimeImmutable();
  }

  /**
   * Method bindTeam.
   *
   * Binds the channel to an organization team. The participant set is then
   * reconciled from `TeamDirectoryPort` by the command handler (a full
   * resync on bind, event-driven incremental sync afterward) — this method
   * only records the binding itself.
   *
   * @since 1.0.0
   *
   * @param string $teamId the organization team identifier
   */
  public function bindTeam(string $teamId): void
  {
    $this->teamId = $teamId;
    $this->updatedAt = new DateTimeImmutable();
  }

  /**
   * Method unbindTeam.
   *
   * Unbinds the channel from its team. The current participant snapshot is
   * RETAINED as manual participants — a deliberate product decision,
   * documented in `MODULE.md` — this method only clears the binding.
   *
   * @since 1.0.0
   */
  public function unbindTeam(): void
  {
    $this->teamId = null;
    $this->updatedAt = new DateTimeImmutable();
  }

  /**
   * Method setParent.
   *
   * Sets (or clears, when `$parentConversationId` is null) the channel this
   * one is nested under (L2.6). Only meaningful for `subjectType=CHANNEL`
   * conversations — validated by
   * {@see \Messaging\Application\Service\MessagingChannelHierarchyGuard}
   * BEFORE this is called (cycle detection, cross-organization rejection,
   * max-nesting-depth), never here: this method only records the already-
   * validated value, mirroring {@see self::bindTeam()}/{@see self::rename()}.
   *
   * @since 1.1.0
   *
   * @param ?string $parentConversationId the parent channel's identifier, or null to detach
   */
  public function setParent(?string $parentConversationId): void
  {
    $this->parentConversationId = $parentConversationId;
    $this->updatedAt = new DateTimeImmutable();
  }

  /**
   * Method parentConversationId.
   *
   * @since 1.1.0
   *
   * @return ?string the parent channel's identifier, null when top-level or not a channel
   */
  public function parentConversationId(): ?string
  {
    return $this->parentConversationId;
  }

  /**
   * Method id.
   *
   * @since 1.0.0
   */
  public function id(): ConversationId
  {
    return $this->id;
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
   * Method subjectType.
   *
   * @since 1.0.0
   */
  public function subjectType(): MessagingSubjectType
  {
    return $this->subjectType;
  }

  /**
   * Method subjectId.
   *
   * @since 1.0.0
   */
  public function subjectId(): ?string
  {
    return $this->subjectId;
  }

  /**
   * Method visibility.
   *
   * @since 1.0.0
   */
  public function visibility(): ConversationVisibility
  {
    return $this->visibility;
  }

  /**
   * Method lastMessageAt.
   *
   * @since 1.0.0
   */
  public function lastMessageAt(): ?DateTimeImmutable
  {
    return $this->lastMessageAt;
  }

  /**
   * Method messagesCount.
   *
   * @since 1.0.0
   */
  public function messagesCount(): int
  {
    return $this->messagesCount;
  }

  /**
   * Method isArchived.
   *
   * @since 1.0.0
   */
  public function isArchived(): bool
  {
    return $this->isArchived;
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

  /**
   * Method name.
   *
   * @since 1.0.0
   *
   * @return ?ChannelName the channel display name, null for a subject thread
   */
  public function name(): ?ChannelName
  {
    return $this->name;
  }

  /**
   * Method teamId.
   *
   * @since 1.0.0
   *
   * @return ?string the bound organization team identifier, if any
   */
  public function teamId(): ?string
  {
    return $this->teamId;
  }

  /**
   * Method createdByMemberId.
   *
   * @since 1.0.0
   *
   * @return ?string the creating member's identifier, null for a subject thread
   */
  public function createdByMemberId(): ?string
  {
    return $this->createdByMemberId;
  }
  // #endregion
}
