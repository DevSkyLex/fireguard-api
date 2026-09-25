<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Exception;

use RuntimeException;

/** A persisted inspection datetime could not be reinterpreted. */
final class StoredDateTimeReinterpretationException extends RuntimeException
{
}
