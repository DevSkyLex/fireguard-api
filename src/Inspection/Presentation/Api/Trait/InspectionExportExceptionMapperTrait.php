<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Trait;

use Inspection\Domain\Exception\{InspectionAccessDeniedException, InspectionExportTooLargeException, InspectionNotFoundException};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, NotFoundHttpException, UnprocessableEntityHttpException};
use Throwable;

/**
 * Trait InspectionExportExceptionMapperTrait.
 *
 * Unwraps the bus-wrapped exception chain a CSV export controller may
 * receive from `QueryBusPort::ask()` and maps the three export-specific
 * domain exceptions to their HTTP status — mirrors
 * `Intervention\Presentation\Api\Trait\InterventionWorkflowExceptionMapperTrait`.
 * Kept separate from `InspectionExceptionUnwrapperTrait` (a `find*`-style
 * finder, not a mapper) since the two export controllers need a status, not
 * just the located exception.
 *
 * @category Trait
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
trait InspectionExportExceptionMapperTrait
{
  /**
   * Method mapExportException.
   *
   * @since 1.0.0
   *
   * @param Throwable $exception the exception value
   *
   * @return Throwable the mapped HTTP exception, or the original exception when no mapping applies
   */
  private function mapExportException(Throwable $exception): Throwable
  {
    $current = $exception;
    do {
      $mapped = match (true) {
        $current instanceof InspectionAccessDeniedException => new AccessDeniedHttpException($current->getMessage(), $exception),
        $current instanceof InspectionNotFoundException => new NotFoundHttpException($current->getMessage(), $exception),
        $current instanceof InspectionExportTooLargeException => new UnprocessableEntityHttpException($current->getMessage(), $exception),
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
