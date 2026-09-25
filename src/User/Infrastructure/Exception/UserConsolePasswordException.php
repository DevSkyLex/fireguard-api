<?php

declare(strict_types=1);

namespace User\Infrastructure\Exception;

use RuntimeException;

/** A password entered in the user console command is invalid. */
final class UserConsolePasswordException extends RuntimeException
{
}
