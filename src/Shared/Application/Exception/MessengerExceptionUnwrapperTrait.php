<?php

declare(strict_types=1);

namespace Shared\Application\Exception;

use Throwable;

/**
 * Trait MessengerExceptionUnwrapperTrait.
 *
 * Provides exception unwrapping for bus dispatch failures.
 * Traverses the chain of previous exceptions to find a specific exception type.
 *
 * @category Trait
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
trait MessengerExceptionUnwrapperTrait
{
  // #region Methods
  /**
   * Method findException.
   *
   * Traverses the exception chain and returns the first exception
   * matching the expected class.
   *
   * @since 1.0.0
   *
   * @template T of Throwable
   *
   * @param Throwable $exception the caught exception
   * @param class-string<T> $expectedClass the expected class to resolve
   *
   * @return ?T the resolved exception, or null if not found
   */
  private function findException(Throwable $exception, string $expectedClass): ?Throwable
  {
    $current = $exception;

    while (null !== $current) {
      if ($current instanceof $expectedClass) {
        return $current;
      }

      $current = $current->getPrevious();
    }

    return null;
  }
  // #endregion
}
