<?php

declare(strict_types=1);

namespace Webhook\Presentation\Api\Trait;

use InvalidArgumentException;
use Organization\Domain\Exception\OrganizationAccessDeniedException;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException, UnprocessableEntityHttpException};
use Throwable;
use Webhook\Domain\Exception\{WebhookDeliveryNotFoundException, WebhookSubscriptionNotFoundException, WebhookValidationException};

/**
 * Trait WebhookExceptionMapperTrait.
 *
 * Maps Webhook domain exceptions (and the cross-module
 * `OrganizationAccessDeniedException` raised by
 * `OrganizationAuthorizationPort::assertGrantedPermissions()`) to their HTTP
 * counterparts, walking the exception chain the same way
 * `Maintenance\Presentation\Api\Trait\MaintenanceExceptionMapperTrait` does
 * so a command-bus-wrapped exception (`HandlerFailedException` /
 * `MessengerRuntimeException`) is unwrapped transparently.
 *
 * @category Trait
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
trait WebhookExceptionMapperTrait
{
  /**
   * Method mapWebhookException.
   *
   * @since 1.0.0
   *
   * @param Throwable $exception the exception value
   *
   * @return Throwable the mapped exception
   */
  private function mapWebhookException(Throwable $exception): Throwable
  {
    $current = $exception;
    do {
      $mapped = match (true) {
        $current instanceof OrganizationAccessDeniedException => new AccessDeniedHttpException($current->getMessage(), $exception),
        $current instanceof WebhookSubscriptionNotFoundException => new NotFoundHttpException($current->getMessage(), $exception),
        $current instanceof WebhookDeliveryNotFoundException => new NotFoundHttpException($current->getMessage(), $exception),
        $current instanceof WebhookValidationException => new UnprocessableEntityHttpException($current->getMessage(), $exception),
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
