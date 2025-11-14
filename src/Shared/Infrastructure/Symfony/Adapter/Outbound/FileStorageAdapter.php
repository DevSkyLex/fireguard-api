<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Symfony\Adapter\Outbound;

use Shared\Application\Port\Outbound\FileStoragePort;
use Shared\Infrastructure\Symfony\Exception\FileStorageException;
use Throwable;

/**
 * Adapter FileStorageAdapter
 * @final
 *
 * Adapter handling file storage operations
 * using PHP's filesystem functions.
 *
 * @category Outbound Adapter
 * @package Shared\Infrastructure\Symfony\Adapter\Outbound
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FileStorageAdapter implements FileStoragePort
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initialize the file storage adapter.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $basePath The base path for file storage.
   */
  public function __construct(
    private readonly string $basePath
  ) {}
  //#endregion

  //#region Methods
  /**
   * Method write
   * {@inheritDoc}
   *
   * Write data to a file.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $path The path to the file.
   * @param string $contents The data to write to the file.
   *
   * @return void No return value.
   *
   * @throws FileStorageException If the file write fails.
   */
  public function write(string $path, string $contents): void
  {
    $fullPath = $this->basePath . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
    $directory = dirname($fullPath);

    if (!is_dir(filename: $directory)) {
      try {
        if (!@mkdir(directory: $directory, recursive: true, permissions: 0775)) {
          throw FileStorageException::directoryCreationFailed(
            path: $directory
          );
        }
      }
      catch (Throwable $exception) {
        throw FileStorageException::directoryCreationFailed(
          path: $directory,
          previous: $exception
        );
      }
    }

    try {
      if (file_put_contents($fullPath, $contents) === false) {
        throw FileStorageException::writeFailed(
          path: $fullPath
        );
      }
    }
    catch (Throwable $exception) {
      throw FileStorageException::writeFailed(
        path: $fullPath,
        previous: $exception
      );
    }
  }

  /**
   * Method read
   * {@inheritDoc}
   *
   * Read data from a file.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $path The path to the file.
   *
   * @return string The file contents.
   *
   * @throws FileStorageException If the file read fails.
   */
  public function read(string $path): string
  {
    $fullPath = $this->basePath . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);

    if (!is_file($fullPath)) throw FileStorageException::readFailed(
      path: $fullPath
    );

    $data = @file_get_contents($fullPath);

    if ($data === false) throw FileStorageException::readFailed(
      path: $fullPath
    );

    return $data;
  }

  /**
   * Method delete
   * {@inheritDoc}
   *
   * Delete a file.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $path The path to the file.
   *
   * @return void No return value.
   *
   * @throws FileStorageException If the file deletion fails.
   */
  public function delete(string $path): void
  {
    $fullPath = $this->basePath . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);

    if (!file_exists($fullPath)) return;

    try {
      if (!unlink($fullPath)) throw FileStorageException::deleteFailed(
        path: $fullPath
      );
    }
    catch (Throwable $exception) {
      throw FileStorageException::deleteFailed(
        path: $fullPath,
        previous: $exception
      );
    }
  }

  /**
   * Method exists
   * {@inheritDoc}
   *
   * Check if a file exists.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $path The path to the file.
   *
   * @return bool True if the file exists, false otherwise.
   */
  public function exists(string $path): bool
  {
    $fullPath = $this->basePath . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);

    return file_exists($fullPath);
  }
  //#endregion
}
