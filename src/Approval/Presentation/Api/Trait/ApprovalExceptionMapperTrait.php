<?php

declare(strict_types=1);

namespace Approval\Presentation\Api\Trait;

use Approval\Domain\Exception\{
  ApprovalRequestNotFoundException,
  ApprovalRequestNotPendingException,
  ApproverNotAuthorizedException,
  DeferredActionNoLongerApplicableException,
  SelfApprovalNotAllowedException
};
use InvalidArgumentException;
use Organization\Domain\Exception\OrganizationAccessDeniedException;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException, NotFoundHttpException};
use Throwable;

/**
 * Trait ApprovalExceptionMapperTrait.
 *
 * Maps Approval domain exceptions (and the cross-module
 * `OrganizationAccessDeniedException` raised by
 * `OrganizationAuthorizationPort::assertGrantedPermissions()`) to their HTTP
 * counterparts, walking the exception chain the same way
 * `Webhook\Presentation\Api\Trait\WebhookExceptionMapperTrait` does so a
 * command-bus-wrapped exception (`HandlerFailedException` /
 * `MessengerRuntimeException`) is unwrapped transparently.
 *
 * @category Trait
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
trait ApprovalExceptionMapperTrait
{
  /**
   * Method mapApprovalException.
   *
   * @since 1.0.0
   *
   * @param Throwable $exception the exception value
   *
   * @return Throwable the mapped exception
   */
  private function mapApprovalException(Throwable $exception): Throwable
  {
    $current = $exception;
    do {
      $mapped = match (true) {
        $current instanceof OrganizationAccessDeniedException,
        $current instanceof SelfApprovalNotAllowedException,
        $current instanceof ApproverNotAuthorizedException => new AccessDeniedHttpException($current->getMessage(), $exception),
        $current instanceof ApprovalRequestNotFoundException => new NotFoundHttpException($current->getMessage(), $exception),
        $current instanceof ApprovalRequestNotPendingException,
        $current instanceof DeferredActionNoLongerApplicableException => new ConflictHttpException($current->getMessage(), $exception),
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
