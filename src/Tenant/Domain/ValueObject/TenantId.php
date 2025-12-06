<?php

declare(strict_types=1);

namespace Tenant\Domain\ValueObject;

use Shared\Domain\ValueObject\Uuid;

/**
 * ValueObject TenantId
 * @final
 *
 * Represents a unique tenant identifier.
 * Inherits UUID v4 generation from parent via Late Static Binding.
 *
 * @category ValueObject
 * @package Tenant\Domain\ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TenantId extends Uuid
{
}
