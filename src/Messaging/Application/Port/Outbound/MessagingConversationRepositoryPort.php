<?php

declare(strict_types=1);

namespace Messaging\Application\Port\Outbound;

use DateTimeImmutable;
use Messaging\Application\Contract\Channel\{ChannelPage, ChannelView};
use Messaging\Application\Contract\Conversation\{ConversationPage, ConversationView};
use Messaging\Domain\Model\Conversation\Conversation;
use Messaging\Domain\ValueObject\{ConversationVisibility, MessagingSubjectType};

/**
 * Port MessagingConversationRepositoryPort.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface MessagingConversationRepositoryPort
{
  // #region Methods
  /**
   * Method getOrCreate.
   *
   * Returns the existing conversation for the (organization, subjectType,
   * subjectId) triple, or creates it. Implementations MUST use a raw DBAL
   * insert (`ON CONFLICT DO NOTHING`, or catching
   * `UniqueConstraintViolationException`) followed by a select — never
   * ORM `persist()`/`flush()` — so two concurrent requests never create a
   * duplicate row nor close the EntityManager on the unique-constraint
   * violation (mirrors `AutomationRunRepository::reserveRun()`). Used for
   * `subjectType!=CHANNEL` (v1 subject-thread) conversations AND
   * `subjectType=DIRECT` (L2.4) conversations — a channel is created
   * through {@see self::createChannel()} instead. `$visibility` is an
   * explicit parameter (never defaulted/hardcoded by the implementation):
   * a subject-thread conversation passes `ConversationVisibility::SUBJECT`,
   * a direct conversation passes `ConversationVisibility::PARTICIPANTS` —
   * hardcoding it to `SUBJECT` here would silently defeat
   * participant-based access control for every other caller.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   * @param MessagingSubjectType $subjectType the subject type
   * @param ?string $subjectId the subject identifier
   * @param ConversationVisibility $visibility the visibility to persist when the conversation does not exist yet
   *
   * @return ConversationView the existing or newly created conversation
   */
  public function getOrCreate(string $organizationId, MessagingSubjectType $subjectType, ?string $subjectId, ConversationVisibility $visibility): ConversationView;

  /**
   * Method findById.
   *
   * @since 1.0.0
   *
   * @param string $id the conversation identifier
   *
   * @return ?ConversationView the conversation view, or null when not found
   */
  public function findById(string $id): ?ConversationView;

  /**
   * Method findAggregateById.
   *
   * Loads the conversation as a domain aggregate for a command handler that
   * needs to mutate it (archive/unarchive, channel rename/bind team).
   * Read-only call sites should use {@see self::findById()} instead.
   *
   * @since 1.0.0
   *
   * @param string $id the conversation identifier
   *
   * @return ?Conversation the conversation aggregate, or null when not found
   */
  public function findAggregateById(string $id): ?Conversation;

  /**
   * Method list.
   *
   * Lists an organization's SUBJECT-THREAD conversations with optional
   * filters, most recently active first — channels (`subjectType=CHANNEL`)
   * are always excluded (listed instead through
   * {@see self::listChannelsForMember()}), and direct conversations
   * (`subjectType=DIRECT`) are always excluded too (L2.4 — a private 1-to-1
   * conversation must never surface through the organization-wide
   * `/api/conversations` list), so `/api/conversations` stays byte-for-byte
   * the v1 contract. When `$unreadForMemberId` is provided,
   * only conversations with activity since that member's read marker (or
   * with no marker at all) are returned — a coarse "has unread" filter
   * pushed down to SQL so pagination stays correct; the precise,
   * author-excluding per-conversation unread count is a separate concern
   * (see {@see \Messaging\Application\Port\Outbound\MessagingReadMarkerRepositoryPort::unreadCounts()}).
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   * @param ?MessagingSubjectType $subjectType optional subject type filter
   * @param ?string $subjectId optional subject identifier filter
   * @param ?bool $isArchived optional archived-flag filter
   * @param ?string $unreadForMemberId optional member identifier to filter to conversations with unread activity
   * @param int $page the page value
   * @param int $itemsPerPage the items per page value
   *
   * @return ConversationPage the conversation page result
   */
  public function list(
    string $organizationId,
    ?MessagingSubjectType $subjectType,
    ?string $subjectId,
    ?bool $isArchived,
    ?string $unreadForMemberId,
    int $page,
    int $itemsPerPage,
  ): ConversationPage;

  /**
   * Method save.
   *
   * Persists a mutated conversation aggregate (archive/unarchive, channel
   * rename/bind team/unbind team) and returns its fresh view.
   *
   * @since 1.0.0
   *
   * @param Conversation $conversation the conversation aggregate
   *
   * @return ConversationView the persisted conversation view
   */
  public function save(Conversation $conversation): ConversationView;

  /**
   * Method touchOnNewMessage.
   *
   * Atomically increments `messages_count` and sets `last_message_at` for a
   * conversation receiving a new message. A single `UPDATE` statement, not a
   * load-modify-save cycle, so concurrent posts never lose an increment.
   *
   * @since 1.0.0
   *
   * @param string $conversationId the conversation identifier
   * @param DateTimeImmutable $at the new message's creation date
   */
  public function touchOnNewMessage(string $conversationId, DateTimeImmutable $at): void;

  /**
   * Method createChannel.
   *
   * Persists a brand new channel aggregate (a fresh UUID never collides,
   * unlike {@see self::getOrCreate()}'s natural-key race — a plain ORM
   * `persist()`/`flush()` is safe here).
   *
   * @since 1.0.0
   *
   * @param Conversation $channel the channel aggregate (`subjectType=CHANNEL`)
   *
   * @return ConversationView the persisted channel view
   */
  public function createChannel(Conversation $channel): ConversationView;

  /**
   * Method findChannelById.
   *
   * @since 1.0.0
   *
   * @param string $id the channel (conversation) identifier
   *
   * @return ?ChannelView the channel view, or null when not found (or not a channel)
   */
  public function findChannelById(string $id): ?ChannelView;

  /**
   * Method listChannelsForMember.
   *
   * Lists the channels a member participates in (an INNER JOIN on
   * `messaging_participants`), most recently active first.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   * @param string $memberId the participating member's identifier
   * @param ?bool $isArchived optional archived-flag filter
   * @param int $page the page value
   * @param int $itemsPerPage the items per page value
   *
   * @return ChannelPage the channel page result
   */
  public function listChannelsForMember(string $organizationId, string $memberId, ?bool $isArchived, int $page, int $itemsPerPage): ChannelPage;

  /**
   * Method listDirectConversationsForMember.
   *
   * Lists the DIRECT (1-to-1) conversations a member participates in — an
   * INNER JOIN on `messaging_participants` scoped to `subjectType=DIRECT`,
   * most recently active first. Mirrors {@see self::listChannelsForMember()}
   * byte for byte (same join shape, same ordering expression), which is what
   * preserves the privacy invariant behind excluding `DIRECT` from
   * {@see self::list()}: a member can only ever see the direct conversations
   * THEY are a participant of, never another member's.
   *
   * @since 1.4.0
   *
   * @param string $organizationId the owning organization identifier
   * @param string $memberId the participating member's identifier
   * @param ?bool $isArchived optional archived-flag filter
   * @param int $page the page value
   * @param int $itemsPerPage the items per page value
   *
   * @return ConversationPage the direct conversation page result
   */
  public function listDirectConversationsForMember(string $organizationId, string $memberId, ?bool $isArchived, int $page, int $itemsPerPage): ConversationPage;

  /**
   * Method findChannelIdsBoundToTeam.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   * @param string $teamId the organization team identifier
   *
   * @return list<string> the channel (conversation) identifiers currently bound to the team
   */
  public function findChannelIdsBoundToTeam(string $organizationId, string $teamId): array;

  /**
   * Method findSubjectTypesByIds.
   *
   * Batch-resolves the `subjectType` of several conversations in one query —
   * used by consumers (e.g. `MessagingInboxSourceProviderAdapter`) that need
   * to route a set of candidate conversations to the right access rule
   * (subject-thread permission vs. channel participation) without issuing a
   * lookup per conversation.
   *
   * @since 1.0.0
   *
   * @param list<string> $conversationIds the conversation identifiers
   *
   * @return array<string, string> the subject type value indexed by conversation id; unknown ids are simply absent
   */
  public function findSubjectTypesByIds(array $conversationIds): array;
  // #endregion
}
