<?php

declare(strict_types=1);

namespace Inspection\Application\Contract\Export;

use RuntimeException;

/**
 * Class InspectionExportFailureException
 *
 * Identifies an unusable export buffer or committed document while preserving the existing runtime failure contract.
 *
 * @category Exception
 */
final class InspectionExportFailureException extends RuntimeException
{
}
