<?php

declare(strict_types=1);

namespace Intervention\Application\Exception;

use RuntimeException;

/** The workflow gateway did not return a valid intervention draft. */
final class InterventionDraftCreationException extends RuntimeException
{
}
