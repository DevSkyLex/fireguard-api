<?php

declare(strict_types=1);

namespace User\Infrastructure\Exception;

use RuntimeException;

/** The user console command cannot find the requested user. */
final class UserConsoleNotFoundException extends RuntimeException
{
}
