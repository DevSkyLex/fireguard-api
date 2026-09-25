<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Exception;

/** Invalid DATABASE_STORAGE_TIMEZONE configuration used by persistence adapters. */
final class InvalidStorageTimeZoneException extends InfrastructureException
{
}
