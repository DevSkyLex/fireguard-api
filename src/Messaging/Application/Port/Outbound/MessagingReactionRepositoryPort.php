<?php

declare(strict_types=1);

namespace Messaging\Application\Port\Outbound;

use DateTimeImmutable;
use Messaging\Application\Contract\Reaction\MessageReactionView;

/**
 * Port MessagingReactionRepositoryPort.
 *
 * Manages `messaging_reactions` rows, composite-keyed
 * `(message_id, member_id, emoji)`. That key is what makes reacting an
 * idempotent insert and un-reacting a plain delete — implementations MUST
 * NEVER load-then-save a row (no read-modify-write), which is the only way
 * to guarantee no lost update under concurrent reactions on the same
 * message.
 *
 * @category Port
 *
 * @version 1.1.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface MessagingReactionRepositoryPort
{
  // #region Methods
  /**
   * Method add.
   *
   * Adds a reaction. Implementations MUST be idempotent (a raw DBAL insert
   * with the unique-constraint violation swallowed, or
   * `ON CONFLICT DO NOTHING`) — never ORM `persist()`/`flush()`, which would
   * close the EntityManager on the conflict (mirrors
   * `MessagingParticipantRepository::addParticipant()`).
   *
   * @since 1.1.0
   *
   * @param string $messageId the owning message identifier
   * @param string $organizationId the owning organization identifier
   * @param string $memberId the reacting member's identifier
   * @param string $emoji the reaction emoji
   * @param DateTimeImmutable $createdAt the reaction date
   */
  public function add(string $messageId, string $organizationId, string $memberId, string $emoji, DateTimeImmutable $createdAt): void;

  /**
   * Method remove.
   *
   * Removes a reaction. Idempotent (a no-op when the member never reacted
   * with that emoji).
   *
   * @since 1.1.0
   *
   * @param string $messageId the owning message identifier
   * @param string $memberId the reacting member's identifier
   * @param string $emoji the reaction emoji
   */
  public function remove(string $messageId, string $memberId, string $emoji): void;

  /**
   * Method findByMessageIds.
   *
   * Batch-loads every reaction on any of the given messages, in a single
   * query — used to populate `MessageOutput::$reactions` for a whole message
   * page without one query per message (N+1), mirroring
   * `MessagingAttachmentRepositoryPort::findByMessageIds()`.
   *
   * @since 1.1.0
   *
   * @param list<string> $messageIds the owning message identifiers
   *
   * @return list<MessageReactionView> the flat, unaggregated reaction list
   */
  public function findByMessageIds(array $messageIds): array;
  // #endregion
}
