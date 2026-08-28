<?php

declare(strict_types=1);

namespace Calendar\Presentation\Api\Trait;

use Calendar\Domain\Exception\{CalendarEventNotFoundException, CalendarEventValidationException, CalendarFeedTokenNotFoundException};
use InvalidArgumentException;
use Organization\Domain\Exception\OrganizationAccessDeniedException;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException, UnprocessableEntityHttpException};
use Throwable;

/**
 * Trait CalendarExceptionMapperTrait.
 *
 * Maps Calendar domain exceptions (and the cross-module
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
trait CalendarExceptionMapperTrait
{
  /**
   * Method mapCalendarException.
   *
   * @since 1.0.0
   *
   * @param Throwable $exception the exception value
   *
   * @return Throwable the mapped exception
   */
  private function mapCalendarException(Throwable $exception): Throwable
  {
    $current = $exception;
    do {
      $mapped = match (true) {
        $current instanceof OrganizationAccessDeniedException => new AccessDeniedHttpException($current->getMessage(), $exception),
        $current instanceof CalendarEventNotFoundException => new NotFoundHttpException($current->getMessage(), $exception),
        $current instanceof CalendarFeedTokenNotFoundException => new NotFoundHttpException($current->getMessage(), $exception),
        $current instanceof CalendarEventValidationException => new UnprocessableEntityHttpException($current->getMessage(), $exception),
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
