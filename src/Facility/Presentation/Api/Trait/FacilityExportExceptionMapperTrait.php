<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Trait;

use Facility\Domain\Exception\{FacilityAccessDeniedException, FacilityExportTooLargeException, FacilityNotFoundException};
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException, UnprocessableEntityHttpException};
use Throwable;

/**
 * Trait FacilityExportExceptionMapperTrait.
 *
 * Unwraps the bus envelope (`MessengerRuntimeException`/`HandlerFailedException`)
 * around a domain exception thrown by {@see \Facility\Application\UseCase\Query\ExportFacilities\ExportFacilitiesHandler}
 * and maps it to the matching HTTP exception — mirrors
 * `Intervention\...\InterventionWorkflowExceptionMapperTrait`. Kept as an
 * explicit controller-side mapper, exactly like its Intervention export
 * counterpart, rather than relying solely on the central
 * `api_platform.exception_to_status` configuration: the query bus wraps this
 * controller's exception the same way, so a direct `catch` of the domain
 * exception never matches without first unwinding the chain here.
 *
 * @category Trait
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
trait FacilityExportExceptionMapperTrait
{
  /**
   * Method mapExportException.
   *
   * @since 1.0.0
   *
   * @param Throwable $exception the exception value
   *
   * @return Throwable the mapped HTTP exception, or the original when no domain exception was found
   */
  private function mapExportException(Throwable $exception): Throwable
  {
    $current = $exception;
    do {
      $mapped = match (true) {
        $current instanceof FacilityAccessDeniedException => new AccessDeniedHttpException($current->getMessage(), $exception),
        $current instanceof FacilityNotFoundException => new NotFoundHttpException($current->getMessage(), $exception),
        $current instanceof FacilityExportTooLargeException => new UnprocessableEntityHttpException($current->getMessage(), $exception),
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
