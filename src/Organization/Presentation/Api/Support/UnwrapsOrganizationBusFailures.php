<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Support;

use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Throwable;

/**
 * Trait UnwrapsOrganizationBusFailures.
 *
 * The command and query bus adapters wrap every handler failure into a
 * MessengerRuntimeException whose cause is Symfony's HandlerFailedException,
 * so a processor or provider that catches a domain exception straight off
 * dispatch()/ask() never sees it and the intended status code falls through
 * to a 500. Consumers catch MessengerRuntimeException and use this helper to
 * walk the wrapped chain — the previous-exception chain plus the per-handler
 * exceptions Messenger collects — and recover the domain failure they map.
 *
 * @category Trait
 *
 * @version 1.1.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
trait UnwrapsOrganizationBusFailures
{
  // #region Methods
  /**
   * Method findWrappedException.
   *
   * Walks the exception chain — including the exceptions a
   * HandlerFailedException collects per handler — and returns the first one
   * matching the expected class.
   *
   * @since 1.0.0
   *
   * @template T of Throwable
   *
   * @param Throwable $exception the caught bus failure
   * @param class-string<T> $expectedClass the domain exception class to recover
   *
   * @return ?T the recovered exception, or null when the chain holds none
   */
  private function findWrappedException(Throwable $exception, string $expectedClass): ?Throwable
  {
    $current = $exception;

    while (null !== $current) {
      if ($current instanceof $expectedClass) {
        return $current;
      }

      if ($current instanceof HandlerFailedException) {
        foreach ($current->getWrappedExceptions() as $wrappedException) {
          if ($wrappedException instanceof $expectedClass) {
            return $wrappedException;
          }
        }
      }

      $current = $current->getPrevious();
    }

    return null;
  }
  // #endregion
}
