<?php

declare(strict_types=1);

namespace Authorization\Domain\ValueObject;

use Shared\Domain\ValueObject\Uuid;

/**
 * ValueObject RoleAssignmentId
 * @final
 *
 * Represents a unique identifier for a RoleAssignment.
 *
 * @category ValueObject
 * @package Authorization\Domain\ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RoleAssignmentId extends Uuid
{
}
