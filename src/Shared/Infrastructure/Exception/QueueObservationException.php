<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Exception;

/**
 * Class QueueObservationException
 *
 * Reports an invalid durable-queue metadata projection without exposing queue payloads.
 *
 * @category Exception
 */
final class QueueObservationException extends InfrastructureException
{
}
