<?php

declare(strict_types=1);

namespace Audit\Infrastructure\Exception;

use RuntimeException;

/** The audit CSV spool could not open, write or read its temporary stream. */
final class AuditCsvSpoolException extends RuntimeException
{
}
