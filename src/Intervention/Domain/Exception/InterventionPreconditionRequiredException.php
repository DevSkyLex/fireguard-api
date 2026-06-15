<?php

declare(strict_types=1);

namespace Intervention\Domain\Exception;

use RuntimeException;

/**
 * Exception InterventionPreconditionRequiredException.
 *
 * Raised when a state-changing mutation is attempted without the optimistic
 * concurrency precondition (the expected revision / If-Match) that the gateway
 * requires to guard against lost updates.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InterventionPreconditionRequiredException extends RuntimeException
{
}
