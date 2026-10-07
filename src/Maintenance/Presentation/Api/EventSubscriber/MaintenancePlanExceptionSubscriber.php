<?php

declare(strict_types=1);

namespace Maintenance\Presentation\Api\EventSubscriber;

use Maintenance\Domain\Exception\{MaintenanceAccessDeniedException, MaintenanceNotFoundException, MaintenanceValidationException};
use Shared\Domain\Exception\InvalidValueException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, HttpExceptionInterface, NotFoundHttpException, UnprocessableEntityHttpException};
use Symfony\Component\HttpKernel\KernelEvents;
use Throwable;
use ValueError;

use function str_contains;

/** Central RFC7807 mapping for the organization-scoped maintenance-plan surface. */
final class MaintenancePlanExceptionSubscriber implements EventSubscriberInterface
{
  public static function getSubscribedEvents(): array
  {
    return [KernelEvents::EXCEPTION => ['onKernelException', 10]];
  }

  public function onKernelException(ExceptionEvent $event): void
  {
    $original = $event->getThrowable();
    if ($original instanceof HttpExceptionInterface || !str_contains($event->getRequest()->getPathInfo(), '/maintenance/')) {
      return;
    }
    $current = $original;
    do {
      $mapped = match (true) {
        $current instanceof MaintenanceNotFoundException => new NotFoundHttpException($current->getMessage(), $original),
        $current instanceof MaintenanceAccessDeniedException => new AccessDeniedHttpException($current->getMessage(), $original),
        $current instanceof MaintenanceValidationException, $current instanceof InvalidValueException, $current instanceof ValueError => new UnprocessableEntityHttpException($current->getMessage(), $original),
        default => null,
      };
      if (null !== $mapped) {
        $event->setThrowable($mapped);

        return;
      }
      $current = $current->getPrevious();
    } while ($current instanceof Throwable);
  }
}
