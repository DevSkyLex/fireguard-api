<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\DataFixtures;

use RuntimeException;

/** Failure to satisfy the opt-in intervention workload fixture prerequisites. */
final class InterventionWorkloadFixtureException extends RuntimeException
{
}
