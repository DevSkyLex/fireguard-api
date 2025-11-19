<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Exception;

use function sprintf;
use Throwable;

/**
 * Exception FileStorageException
 * @final
 *
 * Exception thrown when file storage operations fail.
 *
 * @category Exception
 * @package Shared\Infrastructure\Exception
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FileStorageException extends InfrastructureException
{
  //#region Methods
  /**
   * Method readFailed
   * @static
   *
   * Create an exception for a failed file read.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $path The path that failed to read.
   * @param ?Throwable $previous The underlying exception if any.
   *
   * @return self The created exception instance.
   */
  public static function readFailed(string $path, ?Throwable $previous = null): self
  {
    return new self(
      message: sprintf('Failed to read file "%s".', $path),
      previous: $previous
    );
  }

  /**
   * Method writeFailed
   * @static
   *
   * Create an exception for a failed file write.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $path The path that failed to write.
   * @param ?Throwable $previous The underlying exception if any.
   *
   * @return self The created exception instance.
   */
  public static function writeFailed(string $path, ?Throwable $previous = null): self
  {
    return new self(
      message: sprintf('Failed to write file "%s".', $path),
      previous: $previous
    );
  }

  /**
   * Method deleteFailed
   * @static
   *
   * Create an exception for a failed file deletion.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $path The path that failed to delete.
   * @param ?Throwable $previous The underlying exception if any.
   *
   * @return self The created exception instance.
   */
  public static function deleteFailed(string $path, ?Throwable $previous = null): self
  {
    return new self(
      message: sprintf('Failed to delete file "%s".', $path),
      previous: $previous
    );
  }

  /**
   * Method directoryCreationFailed
   * @static
   *
   * Create an exception for a failed directory creation.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $path The directory path that failed to create.
   * @param ?Throwable $previous The underlying exception if any.
   *
   * @return self The created exception instance.
   */
  public static function directoryCreationFailed(string $path, ?Throwable $previous = null): self
  {
    return new self(
      message: sprintf('Failed to create directory "%s".', $path),
      previous: $previous
    );
  }
  //#endregion
}
