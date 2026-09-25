<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Exception;

use RuntimeException;

/** Security log PII hashing cannot start without its configured HMAC salt. */
final class MissingPiiSaltException extends RuntimeException
{
}
