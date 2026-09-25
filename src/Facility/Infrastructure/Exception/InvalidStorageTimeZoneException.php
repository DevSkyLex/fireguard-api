<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Exception;

use RuntimeException;

/** Invalid database storage time zone for facility persistence. */
final class InvalidStorageTimeZoneException extends RuntimeException
{
}
