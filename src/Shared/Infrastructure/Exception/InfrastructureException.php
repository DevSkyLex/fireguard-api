<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Exception;

use RuntimeException;

/**
 * Exception InfrastructureException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
abstract class InfrastructureException extends RuntimeException
{
    // #region Methods
    /**
     * Method metadata.
     *
     * Returns the metadata of
     * the exception.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed> the metadata of the exception
     */
    public function metadata(): array
    {
        return [];
    }
    // #endregion
}
