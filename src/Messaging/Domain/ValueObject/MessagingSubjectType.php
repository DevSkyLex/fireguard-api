<?php

declare(strict_types=1);

namespace Messaging\Domain\ValueObject;

use function array_column;

/**
 * Enum MessagingSubjectType.
 *
 * The core-entity type a conversation is bound to in v1: every conversation
 * is attached one-to-one to a subject (`organizationId`, `subjectType`,
 * `subjectId`), validated cross-module through the
 * `messaging.subject_resolver` tagged-iterator seam. `CHANNEL` (v2) and
 * `DIRECT` (L2.4) are the two cases with no resolver-backed subject:
 * `visibility` is always `PARTICIPANTS` (see {@see ConversationVisibility})
 * and readership is an explicit `messaging_participants` row, not a
 * subject-derived permission. They differ in what `subjectId` holds:
 * `CHANNEL` leaves it `null`, while `DIRECT` sets it to a deterministic,
 * intentionally opaque pair key (see
 * {@see \Messaging\Domain\Service\DirectConversationKey}) so the existing
 * `UNIQUE (organization_id, subject_type, subject_id)` index gives
 * pair-uniqueness between two members for free — a `getOrCreate()` on
 * either member's side, in either order, resolves to the SAME conversation.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum MessagingSubjectType: string
{
  case FACILITY = 'facility';
  case EQUIPMENT = 'equipment';
  case INTERVENTION = 'intervention';
  case NON_CONFORMITY = 'non_conformity';
  case CHANNEL = 'channel';
  case DIRECT = 'direct';

  // #region Methods
  /**
   * Method values.
   *
   * Returns every supported subject type value.
   *
   * @since 1.0.0
   *
   * @return list<string> the subject type values
   */
  public static function values(): array
  {
    return array_column(self::cases(), 'value');
  }
  // #endregion
}
