<?php

declare(strict_types=1);

namespace User\Domain\ValueObject;

use Shared\Domain\ValueObject\Uuid;

/**
 * ValueObject UserId
 * @final
 *
 * Represents a unique identifier for a User.
 * Inherits UUID v4 generation from parent via Late Static Binding.
 *
 * @category ValueObject
 * @package User\Domain\ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UserId extends Uuid
{
}
