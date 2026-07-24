<?php

declare(strict_types=1);

namespace Messaging\Domain\Exception;

use RuntimeException;

/**
 * Exception MessagingConflictException.
 *
 * Raised when a request is well-formed but conflicts with the CURRENT state
 * of the resource graph — as opposed to {@see MessagingValidationException},
 * which rejects a request whose input is invalid regardless of state. Maps
 * to `409 Conflict` (see `MessagingExceptionMapperTrait`). Introduced for the
 * channel parent/child hierarchy (L2.6): a channel becoming its own ancestor
 * (a cycle) or a hierarchy exceeding its maximum nesting depth are both
 * state-dependent violations — the same edit could be valid or invalid
 * depending on the CURRENT shape of the hierarchy — which is exactly what
 * `409` communicates, versus the `422` used for a parent that is simply the
 * wrong kind of reference (not a channel, or in a different organization).
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class MessagingConflictException extends RuntimeException
{
}
