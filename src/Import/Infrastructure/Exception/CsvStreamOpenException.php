<?php

declare(strict_types=1);

namespace Import\Infrastructure\Exception;

use RuntimeException;

/** An in-memory CSV stream could not be opened. */
final class CsvStreamOpenException extends RuntimeException
{
}
