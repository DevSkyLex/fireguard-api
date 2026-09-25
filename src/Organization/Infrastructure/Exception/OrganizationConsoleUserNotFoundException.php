<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Exception;

use RuntimeException;

/** An organization console command references an absent user. */
final class OrganizationConsoleUserNotFoundException extends RuntimeException
{
}
