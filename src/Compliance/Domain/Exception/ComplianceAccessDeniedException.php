<?php

declare(strict_types=1);

namespace Compliance\Domain\Exception;

use RuntimeException;

/**
 * Exception ComplianceAccessDeniedException.
 *
 * Mirrors `Maintenance\Domain\Exception\MaintenanceAccessDeniedException`:
 * a thin module-local wrapper mapped to a 403 response by the Presentation
 * layer's exception mapper trait.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ComplianceAccessDeniedException extends RuntimeException
{
}
