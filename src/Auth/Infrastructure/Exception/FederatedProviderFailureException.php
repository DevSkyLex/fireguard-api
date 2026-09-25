<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Exception;

use RuntimeException;

/** Provider adapter failure without an application-level federation error code. */
final class FederatedProviderFailureException extends RuntimeException
{
}
