<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Exception;

use Throwable;

use function sprintf;

/**
 * Exception FileStorageException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FileStorageException extends InfrastructureException
{
    // #region Methods
    /**
     * Method readFailed.
     *
     * @static
     *
     * Create an exception for a failed file read.
     *
     * @since 1.0.0
     *
     * @param string     $path     the path that failed to read
     * @param ?Throwable $previous the underlying exception if any
     *
     * @return self the created exception instance
     */
    public static function readFailed(string $path, ?Throwable $previous = null): self
    {
        return new self(
            message: sprintf('Failed to read file "%s".', $path),
            previous: $previous
        );
    }

    /**
     * Method writeFailed.
     *
     * @static
     *
     * Create an exception for a failed file write.
     *
     * @since 1.0.0
     *
     * @param string     $path     the path that failed to write
     * @param ?Throwable $previous the underlying exception if any
     *
     * @return self the created exception instance
     */
    public static function writeFailed(string $path, ?Throwable $previous = null): self
    {
        return new self(
            message: sprintf('Failed to write file "%s".', $path),
            previous: $previous
        );
    }

    /**
     * Method deleteFailed.
     *
     * @static
     *
     * Create an exception for a failed file deletion.
     *
     * @since 1.0.0
     *
     * @param string     $path     the path that failed to delete
     * @param ?Throwable $previous the underlying exception if any
     *
     * @return self the created exception instance
     */
    public static function deleteFailed(string $path, ?Throwable $previous = null): self
    {
        return new self(
            message: sprintf('Failed to delete file "%s".', $path),
            previous: $previous
        );
    }

    /**
     * Method directoryCreationFailed.
     *
     * @static
     *
     * Create an exception for a failed directory creation.
     *
     * @since 1.0.0
     *
     * @param string     $path     the directory path that failed to create
     * @param ?Throwable $previous the underlying exception if any
     *
     * @return self the created exception instance
     */
    public static function directoryCreationFailed(string $path, ?Throwable $previous = null): self
    {
        return new self(
            message: sprintf('Failed to create directory "%s".', $path),
            previous: $previous
        );
    }
    // #endregion
}
