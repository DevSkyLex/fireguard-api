<?php

declare(strict_types=1);

namespace Shared\Application\Log;

use Shared\Application\Contract\Log\LogLevel as PublicLogLevel;

use function class_alias;

class_alias(PublicLogLevel::class, __NAMESPACE__ . '\LogLevel');
