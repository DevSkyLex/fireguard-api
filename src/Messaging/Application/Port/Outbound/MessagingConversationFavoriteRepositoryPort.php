<?php

declare(strict_types=1);

namespace Messaging\Application\Port\Outbound;

use DateTimeImmutable;

/**
 * Port MessagingConversationFavoriteRepositoryPort.
 *
 * Manages `messaging_conversation_favorites` rows, composite-keyed
 * `(conversation_id, member_id)` — a member's private star on a
 * conversation or channel (a channel id IS a conversation id, so this port
 * covers both). Purely a SIDEBAR ORDERING concern: a favorite grants no
 * access and implementations/callers MUST NEVER consult it when authorizing
 * a read — a favorited conversation the member has lost access to must
 * still be denied exactly as if it were never favorited. Mirrors
 * {@see MessagingReactionRepositoryPort}/{@see MessagingSavedMessageRepositoryPort}:
 * a raw idempotent insert and a plain delete, never a read-modify-write.
 *
 * @category Port
 *
 * @version 1.2.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface MessagingConversationFavoriteRepositoryPort
{
  // #region Methods
  /**
   * Method favorite.
   *
   * Favorites a conversation (or channel) for a member. Implementations
   * MUST be idempotent (a raw DBAL insert with the unique-constraint
   * violation swallowed, or `ON CONFLICT DO NOTHING`) — never ORM
   * `persist()`/`flush()`, which would close the EntityManager on the
   * conflict.
   *
   * @since 1.2.0
   *
   * @param string $conversationId the favorited conversation (or channel) identifier
   * @param string $organizationId the owning organization identifier
   * @param string $memberId the favoriting member's identifier
   * @param DateTimeImmutable $favoritedAt the favorite date
   */
  public function favorite(string $conversationId, string $organizationId, string $memberId, DateTimeImmutable $favoritedAt): void;

  /**
   * Method unfavorite.
   *
   * Removes a member's favorite. Idempotent (a no-op when the member never
   * favorited the conversation, or already unfavorited it) — deliberately
   * callable even when the member has since lost read access to the
   * conversation's subject, so a stale favorite never becomes permanently
   * stuck in a member's sidebar.
   *
   * @since 1.2.0
   *
   * @param string $conversationId the favorited conversation (or channel) identifier
   * @param string $memberId the favoriting member's identifier
   */
  public function unfavorite(string $conversationId, string $memberId): void;

  /**
   * Method findFavoritedConversationIds.
   *
   * Batch-loads, among the given conversation ids, the subset favorited by
   * ONE member — used to populate `ConversationOutput::$isFavorite`/
   * `ChannelOutput::$isFavorite` for a whole page without one query per row.
   * Scoped to a single member by construction: a favorite must never be
   * visible to anyone but the member who made it. MUST be called only AFTER
   * the caller's own read authorization already succeeded — this lookup
   * itself performs no access check and must never be treated as one.
   *
   * @since 1.2.0
   *
   * @param string $memberId the member identifier whose favorites are being resolved
   * @param list<string> $conversationIds the candidate conversation (or channel) identifiers
   *
   * @return list<string> the subset of $conversationIds favorited by $memberId
   */
  public function findFavoritedConversationIds(string $memberId, array $conversationIds): array;
  // #endregion
}
