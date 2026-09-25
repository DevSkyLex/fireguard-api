<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Exception;

use RuntimeException;

/** The security log PII HMAC salt is missing. */
final class MissingPiiSaltException extends RuntimeException
{
}
