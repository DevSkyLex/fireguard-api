<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Publication;

use RuntimeException;

/**
 * Class InterventionFactsScopeTooLarge
 *
 * Requires a narrower source scope instead of silently truncating identities or hydrating unbounded history.
 *
 * @category Contract
 */
final class InterventionFactsScopeTooLarge extends RuntimeException
{
}
