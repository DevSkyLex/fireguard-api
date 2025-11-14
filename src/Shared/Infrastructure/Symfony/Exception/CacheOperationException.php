<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Symfony\Exception;

use Shared\Infrastructure\Exception\InfrastructureException;

use function sprintf;
use Throwable;

/**
 * Exception CacheOperationException
 * @extends InfrastructureException
 * @final
 *
 * Exception thrown when a cache operation fails.
 *
 * @category Exception
 * @package Shared\Infrastructure\Symfony\Exception
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CacheOperationException extends InfrastructureException
{
  //#region Methods
  /**
   * Method readFailed
   * @method readFailed(string $key): self
   * @static
   *
   * Create an exception for a failed cache read.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $key The cache key that failed to read.
   *
   * @return self The created exception instance.
   */
  public static function readFailed(string $key, ?Throwable $previous = null): self
  {
    return new self(
      message: sprintf('Failed to read cache entry "%s".', $key),
      previous: $previous
    );
  }

  /**
   * Method writeFailed
   * @method writeFailed(string $key): self
   * @static
   *
   * Create an exception for a failed cache write.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $key The cache key that failed to persist.
   *
   * @return self The created exception instance.
   */
  public static function writeFailed(string $key, ?Throwable $previous = null): self
  {
    return new self(
      message: sprintf('Failed to write cache entry "%s".', $key),
      previous: $previous
    );
  }

  /**
   * Method deleteFailed
   * @method deleteFailed(string $key): self
   * @static
   *
   * Create an exception for a failed
   * cache deletion.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $key The cache key that failed to delete.
   *
   * @return self The created exception instance.
   */
  public static function deleteFailed(string $key, ?Throwable $previous = null): self
  {
    return new self(
      message: sprintf('Failed to delete cache entry "%s".', $key),
      previous: $previous
    );
  }

  /**
   * Method clearFailed
   * @method clearFailed(): self
   * @static
   *
   * Create an exception for a failed
   * cache clear operation.
   *
   * @access public
   * @since 1.0.0
   *
   * @return self The created exception instance.
   */
  public static function clearFailed(?Throwable $previous = null): self
  {
    return new self(
      message: 'Failed to clear cache storage.',
      previous: $previous
    );
  }
  //#endregion
}
