<?php

declare(strict_types=1);

namespace Messaging\Application\Port\Outbound;

use DateTimeImmutable;
use Messaging\Application\Contract\Message\{MessagePage, MessageView};
use Messaging\Domain\Model\Message\Message;

/**
 * Port MessagingMessageRepositoryPort.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface MessagingMessageRepositoryPort
{
  // #region Methods
  /**
   * Method append.
   *
   * Persists a newly created message.
   *
   * @since 1.0.0
   *
   * @param Message $message the message aggregate
   *
   * @return MessageView the persisted message view
   */
  public function append(Message $message): MessageView;

  /**
   * Method findById.
   *
   * @since 1.0.0
   *
   * @param string $id the message identifier
   *
   * @return ?MessageView the message view, or null when not found
   */
  public function findById(string $id): ?MessageView;

  /**
   * Method findAggregateById.
   *
   * Loads the message as a domain aggregate for a command handler that
   * needs to mutate it (edit/tombstone). Read-only call sites should use
   * {@see self::findById()} instead.
   *
   * @since 1.0.0
   *
   * @param string $id the message identifier
   *
   * @return ?Message the message aggregate, or null when not found
   */
  public function findAggregateById(string $id): ?Message;

  /**
   * Method listByConversation.
   *
   * Lists a conversation's ROOT messages (`parentMessageId IS NULL`), oldest
   * first, cursor-paginated by page number (client-controlled page size,
   * mirroring `InterventionActivityPort`). Threaded replies (L2.5) are
   * excluded — fetch them via {@see self::listRepliesByParent()} instead, the
   * conversation Files/Pins-tab pattern. On every conversation created before
   * L2.5 shipped, every row already has `parent_message_id = NULL`, so this
   * filter is a provable no-op there: offsets, ordering and totals are
   * unchanged for pre-existing data (regression-tested, see
   * `MessagingMessageRepositoryRepliesTest`).
   *
   * @since 1.0.0
   *
   * @param string $conversationId the owning conversation identifier
   * @param int $page the page value
   * @param int $itemsPerPage the items per page value
   *
   * @return MessagePage the root message page result
   */
  public function listByConversation(string $conversationId, int $page, int $itemsPerPage): MessagePage;

  /**
   * Method listRepliesByParent.
   *
   * Lists a message's threaded replies (L2.5), oldest first — the "Thread"
   * panel for a given root message. Threading is single-level (enforced by
   * `PostReplyHandler`), so every result here is itself never a reply.
   *
   * @since 1.2.0
   *
   * @param string $parentMessageId the parent (root) message identifier
   * @param int $page the page value
   * @param int $itemsPerPage the items per page value
   *
   * @return MessagePage the reply page result
   */
  public function listRepliesByParent(string $parentMessageId, int $page, int $itemsPerPage): MessagePage;

  /**
   * Method incrementReplyCount.
   *
   * Atomically bumps `reply_count` on the parent message by one — a single
   * `UPDATE`, never a load-modify-save cycle, mirroring
   * `MessagingConversationRepositoryPort::touchOnNewMessage()` — so concurrent
   * replies never lose an increment.
   *
   * @since 1.2.0
   *
   * @param string $parentMessageId the parent (root) message identifier
   */
  public function incrementReplyCount(string $parentMessageId): void;

  /**
   * Method listPinnedByConversation.
   *
   * Lists a conversation's pinned messages, most recently pinned first — the
   * "Pins" tab. MUST filter on `pinnedAt IS NOT NULL` scoped to the
   * conversation, so the query is served by the partial index
   * `idx_messaging_message_pinned (conversation_id, pinned_at) WHERE
   * pinned_at IS NOT NULL`.
   *
   * @since 1.1.0
   *
   * @param string $conversationId the owning conversation identifier
   * @param int $page the page value
   * @param int $itemsPerPage the items per page value
   *
   * @return MessagePage the pinned message page result
   */
  public function listPinnedByConversation(string $conversationId, int $page, int $itemsPerPage): MessagePage;

  /**
   * Method listSavedByMember.
   *
   * Lists a member's saved messages across the WHOLE organization (not
   * scoped to a single conversation) — the "Saved items" list — most
   * recently saved first. INNER JOINs `messaging_saved_messages` to
   * `messaging_messages`, scoped to both the member and the organization
   * (belt-and-braces multi-tenancy: a member never has a saved-message row
   * outside their own organization, but the extra predicate keeps the query
   * self-evidently safe even if that ever changed).
   *
   * @since 1.2.0
   *
   * @param string $organizationId the owning organization identifier
   * @param string $memberId the saving member's identifier
   * @param int $page the page value
   * @param int $itemsPerPage the items per page value
   *
   * @return MessagePage the saved message page result
   */
  public function listSavedByMember(string $organizationId, string $memberId, int $page, int $itemsPerPage): MessagePage;

  /**
   * Method listMentionsForMember.
   *
   * Lists the most recent messages mentioning the given member (an
   * `@{memberUuid}` token in `mentions`), newest first — the source query
   * behind the `inbox.source_provider` mention feed (see
   * `MessagingInboxSourceProviderAdapter`). Excludes tombstoned messages and
   * the member's OWN messages (a member is never notified of mentioning
   * themselves). `$limit` is a hard cap on the query itself, never a
   * post-fetch truncation. A mention alone grants no read access: the
   * caller is responsible for filtering the result down to conversations
   * the member can actually read.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   * @param string $memberId the mentioned member's identifier
   * @param ?DateTimeImmutable $before the cursor: when provided, only messages created strictly before this instant are returned
   * @param int $limit the maximum number of messages to return
   *
   * @return list<MessageView> the mentioning messages, newest first
   */
  public function listMentionsForMember(string $organizationId, string $memberId, ?DateTimeImmutable $before, int $limit): array;

  /**
   * Method save.
   *
   * Persists a mutated message aggregate (edit/tombstone/pin/unpin) and
   * returns its fresh view.
   *
   * @since 1.0.0
   *
   * @param Message $message the message aggregate
   *
   * @return MessageView the persisted message view
   */
  public function save(Message $message): MessageView;
  // #endregion
}
