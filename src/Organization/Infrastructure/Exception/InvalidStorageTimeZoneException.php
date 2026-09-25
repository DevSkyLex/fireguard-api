<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Exception;

use RuntimeException;

/** Invalid database storage time zone for organization persistence. */
final class InvalidStorageTimeZoneException extends RuntimeException
{
}
