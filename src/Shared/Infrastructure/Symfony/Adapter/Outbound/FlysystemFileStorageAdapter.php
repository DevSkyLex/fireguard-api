<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Symfony\Adapter\Outbound;

use League\Flysystem\{FilesystemException, FilesystemOperator};
use Shared\Application\Port\Outbound\FileStoragePort;
use Shared\Infrastructure\Exception\FileStorageException;

/**
 * Adapter FlysystemFileStorageAdapter.
 *
 * Object-storage backed implementation of {@see FileStoragePort}, built on a
 * Flysystem {@see FilesystemOperator}. The operator is resolved from the
 * env-driven `STORAGE_DSN` by {@see \Shared\Infrastructure\Storage\FlysystemFactory},
 * so the same adapter transparently serves a local disk in dev/test or
 * S3/MinIO in staging/prod. Every {@see FilesystemException} raised by the
 * underlying operator is wrapped into the existing {@see FileStorageException}
 * so callers keep the write-then-persist rollback contract unchanged.
 *
 * @category Outbound Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FlysystemFileStorageAdapter implements FileStoragePort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param FilesystemOperator $filesystem the underlying Flysystem operator
   */
  public function __construct(
    private FilesystemOperator $filesystem,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method write
   * {@inheritDoc}
   *
   * @since 1.0.0
   *
   * @param string $path the path to the file
   * @param string $contents the contents of the file
   *
   * @throws FileStorageException if the file write fails
   *
   * @return void no return value
   */
  public function write(string $path, string $contents): void
  {
    try {
      $this->filesystem->write($path, $contents);
    } catch (FilesystemException $exception) {
      throw FileStorageException::writeFailed(
        path: $path,
        previous: $exception,
      );
    }
  }

  /**
   * Method read
   * {@inheritDoc}
   *
   * @since 1.0.0
   *
   * @param string $path the path to the file
   *
   * @throws FileStorageException if the file read fails
   *
   * @return string the contents of the file
   */
  public function read(string $path): string
  {
    try {
      return $this->filesystem->read($path);
    } catch (FilesystemException $exception) {
      throw FileStorageException::readFailed(
        path: $path,
        previous: $exception,
      );
    }
  }

  /**
   * Method delete
   * {@inheritDoc}
   *
   * Delegates idempotency to the underlying Flysystem adapter: both the
   * local adapter and the S3 adapter already no-op (or return success) when
   * the object does not exist, matching the legacy local-disk behaviour.
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
    try {
      $this->filesystem->delete($path);
    } catch (FilesystemException $exception) {
      throw FileStorageException::deleteFailed(
        path: $path,
        previous: $exception,
      );
    }
  }

  /**
   * Method exists
   * {@inheritDoc}
   *
   * @since 1.0.0
   *
   * @param string $path the path to the file
   *
   * @throws FileStorageException if the existence check fails
   *
   * @return bool true if the file exists, false otherwise
   */
  public function exists(string $path): bool
  {
    try {
      return $this->filesystem->fileExists($path);
    } catch (FilesystemException $exception) {
      throw FileStorageException::readFailed(
        path: $path,
        previous: $exception,
      );
    }
  }
  // #endregion
}
