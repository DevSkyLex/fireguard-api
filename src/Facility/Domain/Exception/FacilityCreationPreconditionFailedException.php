<?php

declare(strict_types=1);

namespace Facility\Domain\Exception;

use RuntimeException;

/**
 * Exception FacilityCreationPreconditionFailedException.
 *
 * Preserves a create-only offline identity conflict as a precondition failure.
 *
 * @category Exception
 */
final class FacilityCreationPreconditionFailedException extends RuntimeException
{
}
