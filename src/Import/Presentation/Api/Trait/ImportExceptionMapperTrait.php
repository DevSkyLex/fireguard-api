<?php

declare(strict_types=1);

namespace Import\Presentation\Api\Trait;

use Import\Domain\Exception\ImportJobNotFoundException;
use InvalidArgumentException;
use Organization\Domain\Exception\OrganizationAccessDeniedException;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};
use Throwable;

/**
 * Trait ImportExceptionMapperTrait.
 *
 * Maps Import domain exceptions (and the cross-module
 * `OrganizationAccessDeniedException` raised by
 * `OrganizationAuthorizationPort::assertGrantedPermissions()`) to their HTTP
 * counterparts, mirroring `Maintenance\Presentation\Api\Trait\MaintenanceExceptionMapperTrait`.
 *
 * @category Trait
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
trait ImportExceptionMapperTrait
{
  /**
   * Method mapImportException.
   *
   * @since 1.0.0
   *
   * @param Throwable $exception the exception value
   *
   * @return Throwable the mapped exception
   */
  private function mapImportException(Throwable $exception): Throwable
  {
    $current = $exception;
    do {
      $mapped = match (true) {
        $current instanceof OrganizationAccessDeniedException => new AccessDeniedHttpException($current->getMessage(), $exception),
        $current instanceof ImportJobNotFoundException => new NotFoundHttpException($current->getMessage(), $exception),
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
