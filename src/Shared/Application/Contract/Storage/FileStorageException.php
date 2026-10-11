<?php

declare(strict_types=1);

namespace Shared\Application\Contract\Storage;

use RuntimeException;
use Throwable;

use function sprintf;

/**
 * Exception FileStorageException.
 *
 * @category Exception
 * @version 1.0.0
 *
 * @phpstan-consistent-constructor
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
class FileStorageException extends RuntimeException
{
  // #region Methods
  /**
   * Method metadata
   *
   * Preserves the historical exception metadata contract without exposing an infrastructure base class.
   *
   * @access public
   *
   * @return array<string, mixed> the empty storage failure metadata
   */
  public function metadata(): array
  {
    return [];
  }

  /**
   * Method readFailed.
   *
   * @static
   *
   * Create an exception for a failed file read.
   *
   * @since 1.0.0
   *
   * @param string $path the path that failed to read
   * @param ?Throwable $previous the underlying exception if any
   *
   * @return static the created exception instance
   */
  public static function readFailed(string $path, ?Throwable $previous = null): static
  {
    return new static(
      message: sprintf('Failed to read file "%s".', $path),
      previous: $previous,
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
   * @param string $path the path that failed to write
   * @param ?Throwable $previous the underlying exception if any
   *
   * @return static the created exception instance
   */
  public static function writeFailed(string $path, ?Throwable $previous = null): static
  {
    return new static(
      message: sprintf('Failed to write file "%s".', $path),
      previous: $previous,
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
   * @param string $path the path that failed to delete
   * @param ?Throwable $previous the underlying exception if any
   *
   * @return static the created exception instance
   */
  public static function deleteFailed(string $path, ?Throwable $previous = null): static
  {
    return new static(
      message: sprintf('Failed to delete file "%s".', $path),
      previous: $previous,
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
   * @param string $path the directory path that failed to create
   * @param ?Throwable $previous the underlying exception if any
   *
   * @return static the created exception instance
   */
  public static function directoryCreationFailed(string $path, ?Throwable $previous = null): static
  {
    return new static(
      message: sprintf('Failed to create directory "%s".', $path),
      previous: $previous,
    );
  }
  // #endregion
}
