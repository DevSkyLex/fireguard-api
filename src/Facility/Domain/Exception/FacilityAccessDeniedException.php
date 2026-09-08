<?php

declare(strict_types=1);

namespace Facility\Domain\Exception;

use RuntimeException;

/**
 * Exception FacilityAccessDeniedException.
 *
 * Thrown when the caller is inside the organization's scope but lacks the
 * required `organization.facilities.*` permission — mirrors
 * {@see \Intervention\Domain\Exception\InterventionAccessDeniedException}.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FacilityAccessDeniedException extends RuntimeException
{
}
