<?php

declare(strict_types=1);

namespace Import\Presentation\Api\EventSubscriber;

use Import\Domain\Exception\{ImportAccessDeniedException, ImportConfirmationNotAllowedException, ImportJobNotFoundException};
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/** Stable public import codes; preserves validation and scope errors handled by API Platform. */
final readonly class ImportFailureSubscriber implements EventSubscriberInterface
{
  public static function getSubscribedEvents(): array
  {
    return [KernelEvents::EXCEPTION => ['onException', 10]];
  }

  public function onException(ExceptionEvent $event): void
  {
    $error = $event->getThrowable();
    do {
      $code = match (true) {
        $error instanceof ImportJobNotFoundException => 'import_not_found',
        $error instanceof ImportAccessDeniedException => 'import_permission_required',
        $error instanceof ImportConfirmationNotAllowedException => 'import_confirmation_unavailable',
        default => null,
      };
      if (null !== $code) {
        $status = match (true) {
          $error instanceof ImportJobNotFoundException => 404,
          $error instanceof ImportConfirmationNotAllowedException => 409,
          default => 403,
        };
        $event->setResponse(new JsonResponse([
          'type' => '/errors/' . $code, 'title' => 'Import operation refused',
          'status' => $status, 'code' => $code, 'detail' => $error->getMessage(),
        ], $status, ['Content-Type' => 'application/problem+json']));

        return;
      }
      $error = $error->getPrevious();
    } while (null !== $error);
  }
}
