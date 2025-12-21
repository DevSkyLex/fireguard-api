<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Exception;

use Throwable;

use function sprintf;

/**
 * Exception CacheOperationException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CacheOperationException extends InfrastructureException
{
  // #region Methods
  /**
   * Method readFailed.
   *
   * @static
   *
   * Create an exception for a failed cache read.
   *
   * @since 1.0.0
   *
   * @param string $key the cache key that failed to read
   *
   * @return self the created exception instance
   */
  public static function readFailed(string $key, ?Throwable $previous = null): self
  {
    return new self(
      message: sprintf('Failed to read cache entry "%s".', $key),
      previous: $previous
    );
  }

  /**
   * Method writeFailed.
   *
   * @static
   *
   * Create an exception for a failed cache write.
   *
   * @since 1.0.0
   *
   * @param string $key the cache key that failed to persist
   *
   * @return self the created exception instance
   */
  public static function writeFailed(string $key, ?Throwable $previous = null): self
  {
    return new self(
      message: sprintf('Failed to write cache entry "%s".', $key),
      previous: $previous
    );
  }

  /**
   * Method deleteFailed.
   *
   * @static
   *
   * Create an exception for a failed
   * cache deletion.
   *
   * @since 1.0.0
   *
   * @param string $key the cache key that failed to delete
   *
   * @return self the created exception instance
   */
  public static function deleteFailed(string $key, ?Throwable $previous = null): self
  {
    return new self(
      message: sprintf('Failed to delete cache entry "%s".', $key),
      previous: $previous
    );
  }

  /**
   * Method clearFailed.
   *
   * @static
   *
   * Create an exception for a failed
   * cache clear operation.
   *
   * @since 1.0.0
   *
   * @return self the created exception instance
   */
  public static function clearFailed(?Throwable $previous = null): self
  {
    return new self(
      message: 'Failed to clear cache storage.',
      previous: $previous
    );
  }
  // #endregion
}
