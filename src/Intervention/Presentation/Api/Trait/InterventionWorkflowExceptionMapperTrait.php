<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Trait;

use InvalidArgumentException;
use Intervention\Domain\Exception\{
  InterventionAccessDeniedException,
  InterventionConflictException,
  InterventionNotFoundException,
  InterventionPreconditionFailedException,
  InterventionPreconditionRequiredException,
  InterventionValidationException
};
use Symfony\Component\HttpKernel\Exception\{
  AccessDeniedHttpException,
  BadRequestHttpException,
  ConflictHttpException,
  NotFoundHttpException,
  PreconditionFailedHttpException,
  PreconditionRequiredHttpException,
  UnprocessableEntityHttpException
};
use Throwable;

/**
 * Trait InterventionWorkflowExceptionMapperTrait.
 *
 * @category Trait
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
trait InterventionWorkflowExceptionMapperTrait
{
  /**
   * Method mapWorkflowException.
   *
   * Executes the map workflow exception operation.
   *
   * @since 1.0.0
   *
   * @param Throwable $exception the exception value
   *
   * @return Throwable the map workflow exception result
   */
  private function mapWorkflowException(Throwable $exception): Throwable
  {
    $current = $exception;
    do {
      $mapped = match (true) {
        $current instanceof InterventionAccessDeniedException => new AccessDeniedHttpException($current->getMessage(), $exception),
        $current instanceof InterventionNotFoundException => new NotFoundHttpException($current->getMessage(), $exception),
        $current instanceof InterventionPreconditionRequiredException => new PreconditionRequiredHttpException($current->getMessage(), $exception),
        $current instanceof InterventionPreconditionFailedException => new PreconditionFailedHttpException($current->getMessage(), $exception),
        $current instanceof InterventionValidationException => new UnprocessableEntityHttpException($current->getMessage(), $exception),
        $current instanceof InterventionConflictException => new ConflictHttpException($current->getMessage(), $exception),
        $current instanceof InvalidArgumentException => new BadRequestHttpException($current->getMessage(), $exception),
        default => null,
      };
      if ($mapped instanceof Throwable) {
        return $mapped;
      }
      $current = $current->getPrevious();
    } while ($current instanceof Throwable);

    return $exception;
  }
}
