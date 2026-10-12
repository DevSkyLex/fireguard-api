<?php

declare(strict_types=1);

namespace Maintenance\Application\Exception;

use RuntimeException;

/**
 * Class MaintenanceScheduleExportRenderingException
 *
 * Reports a technical rendering failure before a bounded schedule export can commit.
 *
 * @category Exception
 */
final class MaintenanceScheduleExportRenderingException extends RuntimeException
{
}
