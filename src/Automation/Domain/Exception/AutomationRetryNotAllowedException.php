<?php

declare(strict_types=1);

namespace Automation\Domain\Exception;

use RuntimeException;

/** Exception AutomationRetryNotAllowedException. The action changed or current policy forbids retry. */
final class AutomationRetryNotAllowedException extends RuntimeException
{
}
