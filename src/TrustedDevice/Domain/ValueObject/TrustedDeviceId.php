<?php

declare(strict_types=1);

namespace TrustedDevice\Domain\ValueObject;

use Shared\Domain\ValueObject\Uuid;

/**
 * ValueObject TrustedDeviceId
 * @final
 *
 * Represents a unique identifier for a trusted device.
 *
 * @category ValueObject
 * @package TrustedDevice\Domain\ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TrustedDeviceId extends Uuid
{
}
