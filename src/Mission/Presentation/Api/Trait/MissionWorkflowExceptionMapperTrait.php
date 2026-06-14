<?php

declare(strict_types=1);

namespace Mission\Presentation\Api\Trait;

use InvalidArgumentException;
use Mission\Domain\Exception\{
  MissionAccessDeniedException,
  MissionConflictException,
  MissionNotFoundException,
  MissionPreconditionFailedException,
  MissionValidationException
};
use Symfony\Component\HttpKernel\Exception\{
  AccessDeniedHttpException,
  BadRequestHttpException,
  ConflictHttpException,
  NotFoundHttpException,
  PreconditionFailedHttpException,
  UnprocessableEntityHttpException
};
use Throwable;

/**
 * Trait MissionWorkflowExceptionMapperTrait.
 *
 * @category Trait
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
trait MissionWorkflowExceptionMapperTrait
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
        $current instanceof MissionAccessDeniedException => new AccessDeniedHttpException($current->getMessage(), $exception),
        $current instanceof MissionNotFoundException => new NotFoundHttpException($current->getMessage(), $exception),
        $current instanceof MissionPreconditionFailedException => new PreconditionFailedHttpException($current->getMessage(), $exception),
        $current instanceof MissionValidationException => new UnprocessableEntityHttpException($current->getMessage(), $exception),
        $current instanceof MissionConflictException => new ConflictHttpException($current->getMessage(), $exception),
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
