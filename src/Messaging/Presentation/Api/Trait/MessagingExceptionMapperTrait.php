<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Trait;

use InvalidArgumentException;
use Messaging\Domain\Exception\{MessagingAccessDeniedException, MessagingAttachmentNotFoundException, MessagingConflictException, MessagingNotFoundException, MessagingSubjectNotFoundException, MessagingValidationException};
use Organization\Domain\Exception\OrganizationAccessDeniedException;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException, NotFoundHttpException, UnprocessableEntityHttpException};
use Throwable;

/**
 * Trait MessagingExceptionMapperTrait.
 *
 * Maps Messaging domain exceptions (and the cross-module
 * `OrganizationAccessDeniedException` raised by
 * `OrganizationAuthorizationPort::assertGrantedPermissions()`) to their HTTP
 * counterparts, walking the exception chain (mirrors
 * `MaintenanceExceptionMapperTrait`) so a command-bus-wrapped exception is
 * unwrapped transparently.
 *
 * @category Trait
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
trait MessagingExceptionMapperTrait
{
  /**
   * Method mapMessagingException.
   *
   * @since 1.0.0
   *
   * @param Throwable $exception the exception value
   *
   * @return Throwable the mapped exception
   */
  private function mapMessagingException(Throwable $exception): Throwable
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
