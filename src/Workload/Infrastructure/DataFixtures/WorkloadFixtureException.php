<?php

declare(strict_types=1);

namespace Workload\Infrastructure\DataFixtures;

use RuntimeException;

/** Failure to satisfy the opt-in workload capacity fixture prerequisites. */
final class WorkloadFixtureException extends RuntimeException
{
}
