<?php

declare(strict_types=1);

namespace Shared\Application\Message;

/**
 * Message VoidResult.
 *
 * Neutral result returned for
 * commands handled without a payload.
 *
 * @category Message
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class VoidResult implements ResultMessage
{
}
