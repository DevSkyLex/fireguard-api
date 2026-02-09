<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Symfony\Adapter\Outbound;

use Closure;
use Shared\Application\Port\Outbound\FileStoragePort;
use Shared\Infrastructure\Exception\FileStorageException;
use Throwable;

use function dirname;
use function file_exists;
use function file_put_contents;
use function is_dir;
use function is_file;
use function ltrim;
use function mkdir;
use function unlink;

use const DIRECTORY_SEPARATOR;

/**
 * Adapter FileStorageAdapter.
 *
 * @category Outbound Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FileStorageAdapter implements FileStoragePort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initialize the file storage adapter.
   *
   * @since 1.0.0
   *
   * @param string $basePath the base path for file storage
   * @param Closure|null $fileReader optional reader for file contents
   */
  public function __construct(
    private readonly string $basePath,
    private readonly ?Closure $fileReader = null,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method write
   * {@inheritDoc}
   *
   * Write data to a file.
   *
   * @since 1.0.0
   *
   * @param string $path the path to the file
   * @param string $contents the data to write to the file
   *
   * @throws FileStorageException if the file write fails
   *
   * @return void no return value
   */
  public function write(string $path, string $contents): void
  {
    $fullPath = $this->basePath . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
    $directory = dirname($fullPath);

    if (!is_dir(filename: $directory)) {
      try {
        if (!@mkdir(directory: $directory, recursive: true, permissions: 0775)) {
          throw FileStorageException::directoryCreationFailed(
            path: $directory,
          );
        }
      } catch (Throwable $exception) {
        throw FileStorageException::directoryCreationFailed(
          path: $directory,
          previous: $exception,
        );
      }
    }

    try {
      if (false === file_put_contents($fullPath, $contents)) {
        throw FileStorageException::writeFailed(
          path: $fullPath,
        );
      }
    } catch (Throwable $exception) {
      throw FileStorageException::writeFailed(
        path: $fullPath,
        previous: $exception,
      );
    }
  }

  /**
   * Method read
   * {@inheritDoc}
   *
   * Read data from a file.
   *
   * @since 1.0.0
   *
   * @param string $path the path to the file
   *
   * @throws FileStorageException if the file read fails
   *
   * @return string the file contents
   */
  public function read(string $path): string
  {
    $fullPath = $this->basePath . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);

    if (!is_file($fullPath)) {
      throw FileStorageException::readFailed(
        path: $fullPath,
      );
    }

    $reader = $this->fileReader ?? 'file_get_contents';
    $data = @$reader($fullPath);

    if (false === $data) {
      throw FileStorageException::readFailed(
        path: $fullPath,
      );
    }

    return $data;
  }

  /**
   * Method delete
   * {@inheritDoc}
   *
   * Delete a file.
   *
   * @since 1.0.0
   *
   * @param string $path the path to the file
   *
   * @throws FileStorageException if the file deletion fails
   *
   * @return void no return value
   */
  public function delete(string $path): void
  {
    $fullPath = $this->basePath . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);

    if (!file_exists($fullPath)) {
      return;
    }

    try {
      if (!unlink($fullPath)) {
        throw FileStorageException::deleteFailed(
          path: $fullPath,
        );
      }
    } catch (Throwable $exception) {
      throw FileStorageException::deleteFailed(
        path: $fullPath,
        previous: $exception,
      );
    }
  }

  /**
   * Method exists
   * {@inheritDoc}
   *
   * Check if a file exists.
   *
   * @since 1.0.0
   *
   * @param string $path the path to the file
   *
   * @return bool true if the file exists, false otherwise
   */
  public function exists(string $path): bool
  {
    $fullPath = $this->basePath . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);

    return file_exists($fullPath);
  }
  // #endregion
}
