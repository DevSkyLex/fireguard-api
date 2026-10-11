<?php

declare(strict_types=1);

namespace Shared\Application\Exception;

use function class_alias;

/**
 * Historical exception name retained for existing callers and serialized failures.
 * New consumers import the published contract.
 */
class_alias(\Shared\Application\Contract\Exception\ApplicationException::class, __NAMESPACE__ . '\\ApplicationException');
