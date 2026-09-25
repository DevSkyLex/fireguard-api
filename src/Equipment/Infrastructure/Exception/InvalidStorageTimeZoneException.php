<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\Exception;

use RuntimeException;

/** Invalid database storage time zone for equipment persistence. */
final class InvalidStorageTimeZoneException extends RuntimeException
{
}
