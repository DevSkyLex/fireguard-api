<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Service;

use InvalidArgumentException;
use Messaging\Domain\Exception\{MessagingAccessDeniedException, MessagingAttachmentNotFoundException, MessagingConflictException, MessagingNotFoundException, MessagingRevisionPreconditionException, MessagingSubjectNotFoundException, MessagingValidationException};
use Organization\Application\Contract\Exception\OrganizationAccessDeniedException;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException, NotFoundHttpException, PreconditionFailedHttpException, PreconditionRequiredHttpException, UnprocessableEntityHttpException};
use Throwable;

/**
 * Class MessagingExceptionMapper.
 *
 * Maps Messaging domain exceptions (and the cross-module
 * `OrganizationAccessDeniedException` raised by
 * `OrganizationAuthorizationPort::assertGrantedPermissions()`) to their HTTP
 * counterparts, walking the exception chain (mirrors
 * `MaintenanceExceptionMapperTrait`) so a command-bus-wrapped exception is
 * unwrapped transparently.
 *
 * @category Presentation Service
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MessagingExceptionMapper
{
  /**
   * Method map.
   *
   * @since 1.0.0
   *
   * @param Throwable $exception the exception value
   *
   * @return Throwable the mapped exception
   */
  public function map(Throwable $exception): Throwable
  {
    $current = $exception;
    do {
      $mapped = match (true) {
        $current instanceof MessagingAccessDeniedException => new AccessDeniedHttpException($current->getMessage(), $exception),
        $current instanceof OrganizationAccessDeniedException => new AccessDeniedHttpException($current->getMessage(), $exception),
        $current instanceof MessagingNotFoundException => new NotFoundHttpException($current->getMessage(), $exception),
        $current instanceof MessagingSubjectNotFoundException => new NotFoundHttpException($current->getMessage(), $exception),
        $current instanceof MessagingAttachmentNotFoundException => new NotFoundHttpException($current->getMessage(), $exception),
        $current instanceof MessagingValidationException => new UnprocessableEntityHttpException($current->getMessage(), $exception),
        $current instanceof MessagingConflictException => new ConflictHttpException($current->getMessage(), $exception),
        $current instanceof InvalidArgumentException => new BadRequestHttpException($current->getMessage(), $exception),
        $current instanceof MessagingRevisionPreconditionException => $current->missing
          ? new PreconditionRequiredHttpException($current->getMessage(), $exception)
          : new PreconditionFailedHttpException($current->getMessage(), $exception),
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
