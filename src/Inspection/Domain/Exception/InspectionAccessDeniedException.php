<?php

declare(strict_types=1);

namespace Inspection\Domain\Exception;

use RuntimeException;

/**
 * Exception InspectionAccessDeniedException.
 *
 * Raised when an authenticated caller is a member of the organization but
 * lacks the required permission — mirrors `Intervention\...\InterventionAccessDeniedException`.
 * Distinct from {@see InspectionNotFoundException}, which covers a caller
 * with no active membership at all: the two map to different HTTP statuses
 * (403 vs 404) precisely so a foreign organization never leaks its existence.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InspectionAccessDeniedException extends RuntimeException
{
}
