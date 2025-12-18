<?php

declare(strict_types=1);

namespace OAuth\Domain\ValueObject;

use Shared\Domain\ValueObject\Uuid;

/**
 * ValueObject ConsentId
 * @final
 *
 * Represents a unique consent identifier.
 *
 * @category ValueObject
 * @package Auth\Domain\ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ConsentId extends Uuid
{
}
