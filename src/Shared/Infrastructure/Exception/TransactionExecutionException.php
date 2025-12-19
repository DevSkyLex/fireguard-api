<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Exception;

use Throwable;

/**
 * Exception TransactionExecutionException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class TransactionExecutionException extends InfrastructureException
{
    // #region Factory Methods
    /**
     * Method wrap.
     *
     * @static
     *
     * Wrap a throwable generated during a transactional operation.
     *
     * @since 1.0.0
     *
     * @param Throwable $previous the exception that occurred during the transaction
     *
     * @return self the created exception instance
     */
    public static function wrap(Throwable $previous): self
    {
        return new self(
            message: 'Failed to execute transactional operation.',
            previous: $previous
        );
    }
    // #endregion
}
