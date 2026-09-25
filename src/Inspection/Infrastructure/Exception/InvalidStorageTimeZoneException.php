<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Exception;

use RuntimeException;

/** Invalid database storage time zone for inspection persistence. */
final class InvalidStorageTimeZoneException extends RuntimeException
{
}
