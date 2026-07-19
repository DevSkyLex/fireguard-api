<?php

declare(strict_types=1);

namespace Messaging\Application\Port\Outbound;

use DateTimeImmutable;

/**
 * Port MessagingSavedMessageRepositoryPort.
 *
 * Manages `messaging_saved_messages` rows, composite-keyed `(member_id,
 * message_id)` — a member's private bookmark on a message, exactly like
 * {@see MessagingReactionRepositoryPort} manages `messaging_reactions`.
 * That key is what makes saving an idempotent insert and unsaving a plain
 * delete — implementations MUST NEVER load-then-save a row (no
 * read-modify-write), which is the only way to guarantee no lost update
 * under concurrent calls. A save is private to the saving member and MUST
 * NEVER be visible to, or removable by, any other member.
 *
 * @category Port
 *
 * @version 1.2.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface MessagingSavedMessageRepositoryPort
{
  // #region Methods
  /**
   * Method save.
   *
   * Saves (bookmarks) a message for a member. Implementations MUST be
   * idempotent (a raw DBAL insert with the unique-constraint violation
   * swallowed, or `ON CONFLICT DO NOTHING`) — never ORM
   * `persist()`/`flush()`, which would close the EntityManager on the
   * conflict (mirrors `MessagingReactionRepository::add()`).
   *
   * @since 1.2.0
   *
   * @param string $messageId the saved message identifier
   * @param string $organizationId the owning organization identifier
   * @param string $memberId the saving member's identifier
   * @param DateTimeImmutable $savedAt the save date
   */
  public function save(string $messageId, string $organizationId, string $memberId, DateTimeImmutable $savedAt): void;

  /**
   * Method unsave.
   *
   * Removes a member's save. Idempotent (a no-op when the member never
   * saved the message, or already unsaved it).
   *
   * @since 1.2.0
   *
   * @param string $messageId the saved message identifier
   * @param string $memberId the saving member's identifier
   */
  public function unsave(string $messageId, string $memberId): void;

  /**
   * Method findSavedMessageIds.
   *
   * Batch-loads, among the given message ids, the subset saved by ONE
   * member — used to populate `MessageOutput::$isSaved` for a whole message
   * page without one query per message (N+1), mirroring
   * `MessagingReactionRepositoryPort::findByMessageIds()`. Scoped to a
   * SINGLE member by construction: there is no cross-member variant, since a
   * save must never be visible to anyone but the member who made it.
   *
   * @since 1.2.0
   *
   * @param string $memberId the member identifier whose saves are being resolved
   * @param list<string> $messageIds the candidate message identifiers
   *
   * @return list<string> the subset of $messageIds saved by $memberId
   */
  public function findSavedMessageIds(string $memberId, array $messageIds): array;
  // #endregion
}
