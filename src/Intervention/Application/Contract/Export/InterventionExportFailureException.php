<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Export;

use RuntimeException;

/**
 * Class InterventionExportFailureException
 *
 * Reports an unreadable export buffer or invalid committed document without changing HTTP failure semantics.
 *
 * @category Exception
 */
final class InterventionExportFailureException extends RuntimeException
{
}
