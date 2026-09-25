<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Exception;

use RuntimeException;

/** A proposed facility patch violates the facility's validation contract. */
final class FacilityPatchConflictException extends RuntimeException
{
}
