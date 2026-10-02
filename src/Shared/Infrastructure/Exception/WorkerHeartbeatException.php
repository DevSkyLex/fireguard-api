<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Exception;

/**
 * Class WorkerHeartbeatException
 *
 * Reports that consumer-loop health could not be published to its observation file.
 * Fixed operational messages contain no message payloads or credentials.
 *
 * @category Exception
 */
final class WorkerHeartbeatException extends InfrastructureException
{
}
