<?php

declare(strict_types=1);

namespace Client\Domain\ValueObject;

use Shared\Domain\ValueObject\Uuid;

/**
 * ValueObject ClientId
 * @final
 *
 * Represents a unique identifier for an OAuth client.
 *
 * @category ValueObject
 * @package Client\Domain\ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ClientId extends Uuid
{
  // Inherits all functionality from Uuid
}
