<?php

declare(strict_types=1);

namespace Compliance\Presentation\Api\Trait;

use Compliance\Domain\Exception\{ComplianceAccessDeniedException, ComplianceExportNotEntitledException, ComplianceNotFoundException};
use InvalidArgumentException;
use Organization\Domain\Exception\OrganizationAccessDeniedException;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};
use Throwable;

/**
 * Trait ComplianceExceptionMapperTrait.
 *
 * Maps Compliance domain exceptions (and the cross-module
 * `OrganizationAccessDeniedException` raised by
 * `OrganizationAuthorizationPort::assertGrantedPermissions()`) to their HTTP
 * counterparts, mirroring `Maintenance\Presentation\Api\Trait\MaintenanceExceptionMapperTrait`.
 * Walks the exception chain so a command/query-bus-wrapped exception is
 * unwrapped transparently.
 *
 * @category Trait
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
trait ComplianceExceptionMapperTrait
{
  /**
   * Method mapComplianceException.
   *
   * @since 1.0.0
   *
   * @param Throwable $exception the exception value
   *
   * @return Throwable the mapped exception
   */
  private function mapComplianceException(Throwable $exception): Throwable
  {
    $current = $exception;
    do {
      $mapped = match (true) {
        $current instanceof ComplianceAccessDeniedException => new AccessDeniedHttpException($current->getMessage(), $exception),
        $current instanceof OrganizationAccessDeniedException => new AccessDeniedHttpException($current->getMessage(), $exception),
        $current instanceof ComplianceExportNotEntitledException => new AccessDeniedHttpException($current->getMessage(), $exception),
        $current instanceof ComplianceNotFoundException => new NotFoundHttpException($current->getMessage(), $exception),
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
