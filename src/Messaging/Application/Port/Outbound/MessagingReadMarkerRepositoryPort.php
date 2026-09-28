<?php

declare(strict_types=1);

namespace Messaging\Application\Port\Outbound;

use DateTimeImmutable;
use Messaging\Application\Contract\ReadMarker\ConversationReceiptPosition;

/**
 * Port MessagingReadMarkerRepositoryPort.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface MessagingReadMarkerRepositoryPort
{
  // #region Methods
  /**
   * Method upsert.
   *
   * Creates or updates a member's read marker for a conversation.
   *
   * @since 1.0.0
   *
   * @param string $conversationId the conversation identifier
   * @param string $organizationId the owning organization identifier
   * @param string $memberId the reading member's identifier
   * @param DateTimeImmutable $lastReadAt the read instant
   * @param ?string $lastReadMessageId the last message the member has read, if any
   */
  public function upsert(string $conversationId, string $organizationId, string $memberId, DateTimeImmutable $lastReadAt, ?string $lastReadMessageId): void;

  /**
   * Records an acknowledgement from another client without changing the read position.
   */
  public function markDelivered(string $conversationId, string $organizationId, string $memberId, string $messageId, DateTimeImmutable $deliveredAt): void;

  /**
   * @param list<string> $memberIds current participants only
   *
   * @return list<ConversationReceiptPosition>
   */
  public function receiptPositions(string $conversationId, array $memberIds): array;

  /**
   * Method unreadCounts.
   *
   * Counts, for each of the given conversations, how many messages were
   * posted by a different member after the reading member's last read
   * marker (an unmarked conversation counts every message). Used to enrich
   * `ListConversations`.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   * @param string $memberId the reading member's identifier
   * @param list<string> $conversationIds the conversation identifiers
   *
   * @return array<string, int> unread counts indexed by conversation id
   */
  public function unreadCounts(string $organizationId, string $memberId, array $conversationIds): array;

  /**
   * Method lastReadAtByConversations.
   *
   * Batch-resolves a member's `lastReadAt` marker for several conversations
   * in one query — used by consumers (e.g.
   * `MessagingInboxSourceProviderAdapter`) that must decide, per candidate
   * message, whether it was already read. A conversation absent from the
   * returned map has no marker at all (never read), mirroring
   * {@see self::unreadCounts()}'s "no marker = everything unread" semantics.
   *
   * @since 1.0.0
   *
   * @param string $memberId the reading member's identifier
   * @param list<string> $conversationIds the conversation identifiers
   *
   * @return array<string, DateTimeImmutable> the last-read instant indexed by conversation id; conversations with no marker are absent
   */
  public function lastReadAtByConversations(string $memberId, array $conversationIds): array;
  // #endregion
}
