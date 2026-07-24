<?php

declare(strict_types=1);

namespace Maintenance\Presentation\Api\Trait;

use InvalidArgumentException;
use Maintenance\Domain\Exception\{MaintenanceAccessDeniedException, MaintenanceNotFoundException, MaintenanceValidationException};
use Organization\Domain\Exception\OrganizationAccessDeniedException;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException, UnprocessableEntityHttpException};
use Throwable;

/**
 * Trait MaintenanceExceptionMapperTrait.
 *
 * Maps Maintenance domain exceptions (and the cross-module
 * `OrganizationAccessDeniedException` raised by
 * `OrganizationAuthorizationPort::assertGrantedPermissions()`) to their HTTP
 * counterparts, walking the exception chain the same way
 * `InterventionWorkflowExceptionMapperTrait` does so a command-bus-wrapped
 * exception (`HandlerFailedException` / `MessengerRuntimeException`) is
 * unwrapped transparently.
 *
 * @category Trait
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
trait MaintenanceExceptionMapperTrait
{
  /**
   * Method mapMaintenanceException.
   *
   * @since 1.0.0
   *
   * @param Throwable $exception the exception value
   *
   * @return Throwable the mapped exception
   */
  private function mapMaintenanceException(Throwable $exception): Throwable
  {
    $current = $exception;
    do {
      $mapped = match (true) {
        $current instanceof MaintenanceAccessDeniedException => new AccessDeniedHttpException($current->getMessage(), $exception),
        $current instanceof OrganizationAccessDeniedException => new AccessDeniedHttpException($current->getMessage(), $exception),
        $current instanceof MaintenanceNotFoundException => new NotFoundHttpException($current->getMessage(), $exception),
        $current instanceof MaintenanceValidationException => new UnprocessableEntityHttpException($current->getMessage(), $exception),
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
