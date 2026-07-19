<?php

declare(strict_types=1);

namespace Messaging\Domain\Service;

use function hash;
use function implode;
use function sort;
use function substr;

use const SORT_STRING;

/**
 * Service DirectConversationKey.
 *
 * Derives the deterministic `messaging_conversations.subject_id` used for a
 * 1-to-1 direct conversation between two organization members
 * (`MessagingSubjectType::DIRECT`).
 *
 * Sorting the two member ids lexicographically BEFORE joining them is what
 * makes this a PAIR key rather than a directed one: without the sort,
 * member A starting a conversation with member B and member B starting one
 * with member A would derive two DIFFERENT keys and create two separate
 * conversations, defeating the whole point of reusing
 * `MessagingConversationRepositoryPort::getOrCreate()`'s
 * `UNIQUE (organization_id, subject_type, subject_id)` constraint for
 * pair-uniqueness (and, with it, its swallowed-`UniqueConstraintViolationException`
 * race handling — both members racing to open the same DM for the first
 * time resolve to the SAME row, exactly like the v1 subject-thread path).
 *
 * `subject_id` is `length: 36` (sized for a single UUID), while two UUIDs
 * joined by a separator is 73 characters, so the sorted pair is hashed
 * (SHA-256) and truncated to 32 hex characters — well under the column
 * limit, and collision-safe for this purpose (a truncated cryptographic
 * digest used as a lookup key, not a user-facing identifier).
 *
 * **This is the one intentionally opaque value in the module.** It is
 * never decoded back into the two member ids — it only exists to make the
 * pair reproducible from both directions. A caller must already know both
 * member ids before deriving it; the conversation's actual readership comes
 * exclusively from its `messaging_participants` rows
 * (`visibility=PARTICIPANTS`), never from parsing this key.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class DirectConversationKey
{
  // #region Methods
  /**
   * Method for.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param string $firstMemberId one of the pair's organization member identifiers
   * @param string $secondMemberId the other of the pair's organization member identifiers
   *
   * @return string the deterministic, order-independent pair key
   */
  public static function for(string $firstMemberId, string $secondMemberId): string
  {
    $pair = [$firstMemberId, $secondMemberId];
    sort($pair, SORT_STRING);

    return substr(hash('sha256', implode(':', $pair)), 0, 32);
  }
  // #endregion
}
