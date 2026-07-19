<?php

declare(strict_types=1);

namespace Assistant\Presentation\Api\Trait;

use Assistant\Domain\Exception\{AssistantMessageIllegalStatusTransitionException, AssistantThreadNotFoundException, AssistantValidationException};
use InvalidArgumentException;
use Organization\Domain\Exception\OrganizationAccessDeniedException;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException, NotFoundHttpException, UnprocessableEntityHttpException};
use Throwable;

/**
 * Trait AssistantExceptionMapperTrait.
 *
 * Maps Assistant domain exceptions (and the cross-module
 * `OrganizationAccessDeniedException` raised by
 * `OrganizationAuthorizationPort::assertGrantedPermissions()`) to their HTTP
 * counterparts, walking the exception chain the same way
 * `Approval\Presentation\Api\Trait\ApprovalExceptionMapperTrait` does so a
 * command-bus-wrapped exception (`HandlerFailedException` /
 * `MessengerRuntimeException`) is unwrapped transparently.
 *
 * @category Trait
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
trait AssistantExceptionMapperTrait
{
  /**
   * Method mapAssistantException.
   *
   * @since 1.0.0
   *
   * @param Throwable $exception the exception value
   *
   * @return Throwable the mapped exception
   */
  private function mapAssistantException(Throwable $exception): Throwable
  {
    $current = $exception;
    do {
      $mapped = match (true) {
        $current instanceof OrganizationAccessDeniedException => new AccessDeniedHttpException($current->getMessage(), $exception),
        $current instanceof AssistantThreadNotFoundException => new NotFoundHttpException($current->getMessage(), $exception),
        $current instanceof AssistantMessageIllegalStatusTransitionException => new ConflictHttpException($current->getMessage(), $exception),
        $current instanceof AssistantValidationException => new UnprocessableEntityHttpException($current->getMessage(), $exception),
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
