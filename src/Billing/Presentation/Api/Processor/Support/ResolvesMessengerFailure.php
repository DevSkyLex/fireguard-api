<?php

declare(strict_types=1);

namespace Billing\Presentation\Api\Processor\Support;

use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Throwable;

/**
 * Trait ResolvesMessengerFailure.
 *
 * Unwraps the command bus failure chain (MessengerRuntimeException ->
 * HandlerFailedException -> domain exception) to recover the first matching
 * domain exception so processors can map it to the right HTTP status.
 *
 * @category Trait
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
trait ResolvesMessengerFailure
{
  // #region Methods
  /**
   * Method firstFailureOf.
   *
   * Walks the previous-exception chain and any handler-wrapped exceptions and
   * returns the first instance matching one of the given classes.
   *
   * @since 1.0.0
   *
   * @param Throwable $exception the caught exception
   * @param class-string ...$classes the exception classes to look for
   *
   * @return ?Throwable the first matching exception, or null
   */
  private function firstFailureOf(Throwable $exception, string ...$classes): ?Throwable
  {
    $current = $exception;

    while (null !== $current) {
      foreach ($this->expandFailure($current) as $candidate) {
        foreach ($classes as $class) {
          if ($candidate instanceof $class) {
            return $candidate;
          }
        }
      }

      $current = $current->getPrevious();
    }

    return null;
  }

  /**
   * Method expandFailure.
   *
   * Yields the exception and any handler-wrapped exceptions.
   *
   * @since 1.0.0
   *
   * @param Throwable $exception the exception to expand
   *
   * @return iterable<Throwable> the candidate exceptions
   */
  private function expandFailure(Throwable $exception): iterable
  {
    yield $exception;

    if ($exception instanceof HandlerFailedException) {
      yield from $exception->getWrappedExceptions();
    }
  }
  // #endregion
}
