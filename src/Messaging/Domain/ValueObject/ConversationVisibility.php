<?php

declare(strict_types=1);

namespace Messaging\Domain\ValueObject;

/**
 * Enum ConversationVisibility.
 *
 * `SUBJECT` (v1, the only value in use today): every organization member
 * holding the subject's own read permission (plus
 * `organization.messaging.read`) can read the thread. `PARTICIPANTS`
 * is reserved for v2 channels, where an explicit `messaging_participants`
 * table restricts the readership to a member list — the column already
 * exists so that migration is purely additive.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum ConversationVisibility: string
{
  case SUBJECT = 'subject';
  case PARTICIPANTS = 'participants';
}
