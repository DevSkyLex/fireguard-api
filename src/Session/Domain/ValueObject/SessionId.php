<?php

declare(strict_types=1);

namespace Session\Domain\ValueObject;

use Shared\Domain\ValueObject\Uuid;

/**
 * ValueObject SessionId
 * @final
 *
 * Represents a unique session identifier.
 * Inherits UUID v4 generation from parent via Late Static Binding.
 *
 * @category ValueObject
 * @package Session\Domain\ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SessionId extends Uuid
{
}
