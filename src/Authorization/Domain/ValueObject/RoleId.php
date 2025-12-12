<?php

declare(strict_types=1);

namespace Authorization\Domain\ValueObject;

use Shared\Domain\ValueObject\Uuid;

/**
 * ValueObject RoleId
 * @final
 *
 * Represents a unique identifier for a Role.
 *
 * @category ValueObject
 * @package Authorization\Domain\ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RoleId extends Uuid
{
}
