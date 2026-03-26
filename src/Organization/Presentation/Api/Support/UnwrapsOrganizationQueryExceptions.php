<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Support;

use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Throwable;

trait UnwrapsOrganizationQueryExceptions
{
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
}
