<?php

declare(strict_types=1);

namespace Audit\Infrastructure\Exception;

use RuntimeException;

/** Audit PII hashing cannot start without its configured HMAC salt. */
final class MissingPiiSaltException extends RuntimeException
{
}
