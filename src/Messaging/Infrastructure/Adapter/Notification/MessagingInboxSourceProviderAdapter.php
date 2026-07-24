<?php

declare(strict_types=1);

namespace Messaging\Infrastructure\Adapter\Notification;

use DateTimeImmutable;
use Messaging\Application\Contract\Message\MessageView;
use Messaging\Application\Port\Outbound\{MessagingConversationRepositoryPort, MessagingMemberDirectoryPort, MessagingMessageRepositoryPort, MessagingParticipantRepositoryPort, MessagingReadMarkerRepositoryPort};
use Messaging\Application\Service\MessagingAccessPolicy;
use Messaging\Domain\ValueObject\MessagingSubjectType;
use Notification\Application\Contract\Inbox\InboxItem;
use Notification\Application\Port\Outbound\InboxSourceProviderPort;

use function array_map;
use function array_unique;
use function array_values;
use function in_array;
use function mb_strimwidth;
use function trim;

/**
 * Adapter MessagingInboxSourceProviderAdapter.
 *
 * Messaging's `inbox.source_provider` (see
 * `Notification\Application\Port\Outbound\InboxSourceProviderPort` and
 * `Notification\MODULE.md`'s "Unified Inbox Seam" section): surfaces the
 * mentions (`@{memberUuid}` tokens) a user needs to act on through the
 * unified inbox feed.
 *
 * **Only `mention` is live.** `direct_message` and `thread_reply` are
 * reserved `kind` values (see `Notification\Application\Contract\Inbox\InboxItem::$kind`)
 * with NO backing data model yet — Messaging has no direct-message subject
 * type (`MessagingSubjectType` is `facility|equipment|intervention|non_conformity|channel`)
 * and no threaded replies. When either ships, add a sibling
 * `fetchDirectMessages()`/`fetchThreadReplies()` private method and merge
 * its results into {@see self::fetch()} the same way this class already
 * merges nothing else today — no rewrite of the mention path is needed.
 *
 * A mention alone never grants access: the mentioned member must still pass
 * Messaging's normal read rule for that conversation (the subject's own
 * read permission for a subject-thread conversation, or participation for a
 * channel) — enforced here via {@see MessagingAccessPolicy} and
 * {@see MessagingParticipantRepositoryPort}, never a second access path.
 * Excluding a borderline row is always preferred over including it: an
 * inaccessible or unresolved conversation is silently dropped, not thrown.
 *
 * No N+1: {@see MessagingMessageRepositoryPort::listMentionsForMember()} is
 * the ONE bounded candidate query (organization scope, own-message and
 * tombstone exclusion, cursor, limit all pushed down); the conversations and
 * read markers behind the result are then batch-resolved in two more
 * bounded queries ({@see MessagingConversationRepositoryPort::findSubjectTypesByIds()},
 * {@see MessagingReadMarkerRepositoryPort::lastReadAtByConversations()}) —
 * never one query per conversation.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MessagingInboxSourceProviderAdapter implements InboxSourceProviderPort
{
  // #region Constants
  /**
   * The source key this adapter contributes as.
   *
   * @since 1.0.0
   */
  public const string SOURCE_KEY = 'messaging.mention';

  /**
   * The one live `kind` this adapter currently emits.
   *
   * @since 1.0.0
   */
  private const string KIND_MENTION = 'mention';

  private const string TARGET_TYPE_CONVERSATION = 'conversation';

  private const int SNIPPET_MAX_LENGTH = 160;

  /**
   * Constant UNREAD_COUNT_SCAN_LIMIT.
   *
   * {@see countUnread()} caveat: unlike
   * `NotificationInboxSourceProviderAdapter::countUnread()` (a single SQL
   * `COUNT` against a recipient-scoped column), a mention's readability
   * additionally depends on per-row, permission-based conversation access
   * ({@see resolveAccessibleConversationIds()}) — the same reason
   * {@see fetch()} cannot push its filtering into SQL either. Replicating
   * that access check as a SQL predicate would mean re-deriving RBAC rules
   * in the query layer, which this codebase deliberately never does
   * (permissions are always evaluated in PHP, never as SQL conditions).
   * `countUnread()` therefore reuses the same bounded, access-filtered
   * `fetchMentions()` path capped at this scan window and counts the unread
   * ones within it — an exact count up to the cap, a lower bound beyond it.
   * This is a deliberate, documented trade-off for a badge counter (most UIs
   * cap an unread badge display at "99+" anyway); a follow-up lot could add
   * a dedicated aggregate repository query if exactness beyond the cap ever
   * becomes a real product requirement.
   *
   * @since 1.1.0
   *
   * @var int
   */
  private const int UNREAD_COUNT_SCAN_LIMIT = 200;

  /**
   * Subject type -> required read permission, mirroring the table
   * documented in `Messaging\MODULE.md` ("The `messaging.subject_resolver`
   * tagged-iterator seam"). Deliberately duplicated here instead of calling
   * `MessagingSubjectResolverRegistry::resolve()` per candidate conversation:
   * that registry issues one cross-module EXISTENCE query per subject
   * (Facility/Equipment/Intervention/Inspection), which is exactly the
   * per-row N+1 this adapter must avoid. The four resolver adapters
   * (`FacilityMessagingSubjectResolverAdapter` and siblings) already
   * duplicate these same literal permission strings for the identical
   * reason `MentionExtractor`'s regex is duplicated in `Intervention` — see
   * `Messaging\MODULE.md`. `CHANNEL` is handled separately (participation,
   * not a permission — see {@see self::resolveAccessibleConversationIds()}).
   *
   * @var array<string, string>
   */
  private const array SUBJECT_READ_PERMISSIONS = [
    'facility' => 'organization.facilities.read',
    'equipment' => 'organization.equipment.read',
    'intervention' => 'organization.interventions.read',
    'non_conformity' => 'organization.inspection.read',
  ];
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param MessagingMessageRepositoryPort $messages the message repository port
   * @param MessagingConversationRepositoryPort $conversations the conversation repository port
   * @param MessagingReadMarkerRepositoryPort $readMarkers the read marker repository port
   * @param MessagingParticipantRepositoryPort $participants the participant repository port
   * @param MessagingMemberDirectoryPort $members the member directory port
   * @param MessagingAccessPolicy $accessPolicy the messaging access policy
   */
  public function __construct(
    private MessagingMessageRepositoryPort $messages,
    private MessagingConversationRepositoryPort $conversations,
    private MessagingReadMarkerRepositoryPort $readMarkers,
    private MessagingParticipantRepositoryPort $participants,
    private MessagingMemberDirectoryPort $members,
    private MessagingAccessPolicy $accessPolicy,
  ) {
  }
  // #endregion

  // #region Methods
  public function sourceKey(): string
  {
    return self::SOURCE_KEY;
  }

  public function fetch(string $userId, ?string $organizationId, ?DateTimeImmutable $before, int $limit): array
  {
    // Messaging member identity is per-organization
    // (MessagingMemberDirectoryPort::resolveActiveMemberId() always takes an
    // organization). With no organization to resolve against there is no
    // member id to match `mentions` against, so this source legitimately
    // contributes nothing rather than fanning out across every organization
    // the user happens to belong to.
    if (null === $organizationId) {
      return [];
    }

    if (!$this->accessPolicy->hasReadPermission($userId, $organizationId)) {
      return [];
    }

    $memberId = $this->members->resolveActiveMemberId($organizationId, $userId);
    if (null === $memberId) {
      return [];
    }

    return $this->fetchMentions($userId, $organizationId, $memberId, $before, $limit);
  }

  public function countUnread(string $userId, ?string $organizationId): int
  {
    // Same organization-scoping rationale as fetch(): mention identity is
    // per-organization, so there is nothing to count without one.
    if (null === $organizationId) {
      return 0;
    }

    if (!$this->accessPolicy->hasReadPermission($userId, $organizationId)) {
      return 0;
    }

    $memberId = $this->members->resolveActiveMemberId($organizationId, $userId);
    if (null === $memberId) {
      return 0;
    }

    $items = $this->fetchMentions($userId, $organizationId, $memberId, null, self::UNREAD_COUNT_SCAN_LIMIT);

    $unreadCount = 0;
    foreach ($items as $item) {
      if (!$item->isRead) {
        ++$unreadCount;
      }
    }

    return $unreadCount;
  }

  /**
   * Method fetchMentions.
   *
   * @since 1.0.0
   *
   * @param string $userId the user identifier
   * @param string $organizationId the organization identifier
   * @param string $memberId the resolved active member identifier
   * @param ?DateTimeImmutable $before the cursor
   * @param int $limit the maximum number of items to return
   *
   * @return list<InboxItem> the mention items the member may actually read
   */
  private function fetchMentions(string $userId, string $organizationId, string $memberId, ?DateTimeImmutable $before, int $limit): array
  {
    $candidates = $this->messages->listMentionsForMember($organizationId, $memberId, $before, $limit);
    if ([] === $candidates) {
      return [];
    }

    $conversationIds = array_values(array_unique(array_map(
      static fn (MessageView $message): string => $message->conversationId,
      $candidates,
    )));

    $subjectTypesByConversation = $this->conversations->findSubjectTypesByIds($conversationIds);
    $lastReadAtByConversation = $this->readMarkers->lastReadAtByConversations($memberId, $conversationIds);
    $accessibleConversationIds = $this->resolveAccessibleConversationIds($userId, $organizationId, $memberId, $subjectTypesByConversation);

    $items = [];
    foreach ($candidates as $message) {
      if (!in_array($message->conversationId, $accessibleConversationIds, true)) {
        continue;
      }

      $items[] = $this->toInboxItem($message, $lastReadAtByConversation[$message->conversationId] ?? null);
    }

    return $items;
  }

  /**
   * Method resolveAccessibleConversationIds.
   *
   * Batch-resolves which of the given conversations the member may actually
   * read: a channel (`subjectType=channel`) requires participation (or
   * `.manage`, mirroring {@see MessagingAccessPolicy::assertCanReadChannel()});
   * a subject-thread conversation requires the subject's own read
   * permission (mirroring {@see MessagingAccessPolicy::assertCanReadThread()}).
   * `organization.messaging.read` itself was already asserted by
   * {@see self::fetch()} before any of this ran. An unresolved (deleted
   * mid-flight) or unrecognized subject type is excluded, never granted.
   *
   * @since 1.0.0
   *
   * @param string $userId the user identifier
   * @param string $organizationId the organization identifier
   * @param string $memberId the resolved active member identifier
   * @param array<string, string> $subjectTypesByConversation the candidate conversations' subject types, indexed by conversation id
   *
   * @return list<string> the conversation identifiers the member may read
   */
  private function resolveAccessibleConversationIds(string $userId, string $organizationId, string $memberId, array $subjectTypesByConversation): array
  {
    $manages = $this->accessPolicy->hasManagePermission($userId, $organizationId);
    // ONE bulk lookup of every channel this member participates in, never
    // one `isParticipant()` call per channel.
    $channelIds = $manages ? [] : $this->participants->listChannelIdsForMember($organizationId, $memberId);

    $permissionCache = [];
    $accessible = [];

    foreach ($subjectTypesByConversation as $conversationId => $subjectType) {
      if (MessagingSubjectType::CHANNEL->value === $subjectType) {
        if ($manages || in_array($conversationId, $channelIds, true)) {
          $accessible[] = $conversationId;
        }

        continue;
      }

      $requiredPermission = self::SUBJECT_READ_PERMISSIONS[$subjectType] ?? null;
      if (null === $requiredPermission) {
        // Unknown/foreign subject type: fail closed, never assume access.
        continue;
      }

      if (!isset($permissionCache[$requiredPermission])) {
        $permissionCache[$requiredPermission] = $this->accessPolicy->hasPermission($userId, $organizationId, $requiredPermission);
      }

      if ($permissionCache[$requiredPermission]) {
        $accessible[] = $conversationId;
      }
    }

    return $accessible;
  }

  /**
   * Method toInboxItem.
   *
   * @since 1.0.0
   *
   * @param MessageView $message the mentioning message
   * @param ?DateTimeImmutable $lastReadAt the member's last-read instant for the message's conversation, if any
   *
   * @return InboxItem the mapped inbox item
   */
  private function toInboxItem(MessageView $message, ?DateTimeImmutable $lastReadAt): InboxItem
  {
    return new InboxItem(
      sourceKey: self::SOURCE_KEY,
      id: $message->id,
      kind: self::KIND_MENTION,
      // No subject label (facility name, etc.) is resolved here on
      // purpose: doing so would mean calling back into the subject
      // resolver seam per candidate — the exact per-row cross-module cost
      // this adapter is built to avoid. The snippet already carries the
      // message content; the client can still deep-link via `targetId`.
      title: 'You were mentioned in a conversation',
      snippet: $this->snippet($message->body),
      occurredAt: $message->createdAt,
      isRead: null !== $lastReadAt && $lastReadAt >= $message->createdAt,
      organizationId: $message->organizationId,
      targetType: self::TARGET_TYPE_CONVERSATION,
      targetId: $message->conversationId,
    );
  }

  /**
   * Method snippet.
   *
   * @since 1.0.0
   *
   * @param string $body the persisted message body
   *
   * @return ?string a bounded preview of the body, or null when blank
   */
  private function snippet(string $body): ?string
  {
    $trimmed = trim($body);

    return '' !== $trimmed ? mb_strimwidth($trimmed, 0, self::SNIPPET_MAX_LENGTH, '…') : null;
  }
  // #endregion
}
