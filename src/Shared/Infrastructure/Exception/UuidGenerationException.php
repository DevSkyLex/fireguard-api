<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Exception;

use Throwable;

use function sprintf;

/**
 * Exception UuidGenerationException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class UuidGenerationException extends InfrastructureException
{
    // #region Methods
    /**
     * Method dueToRandomFailure.
     *
     * @static
     *
     * Create an exception when the random source fails.
     *
     * @since 1.0.0
     *
     * @param Throwable $previous the underlying exception triggered by the generator
     *
     * @return self the created exception instance
     */
    public static function dueToRandomFailure(Throwable $previous): self
    {
        return new self(
            message: sprintf('Unable to generate a UUID: %s', $previous->getMessage()),
            previous: $previous
        );
    }
    // #endregion
}
