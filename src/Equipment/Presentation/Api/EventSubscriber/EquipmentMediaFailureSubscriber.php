<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\EventSubscriber;

use Equipment\Domain\Exception\{AttachmentNotFoundException, EquipmentAccessDeniedException, EquipmentMediaIdentityConflictException, EquipmentMediaNotFoundException, EquipmentNotFoundException, EquipmentReportNotEntitledException, EquipmentRequestInvalidException};
use Intervention\Application\Contract\Resource\{InterventionAccessDeniedException, InterventionConflictException, InterventionNotFoundException, InterventionResourceNotFoundException};
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/** Maps contextual use-case refusals after bus dispatch. @category Subscriber @version 1.0.0 @author Valentin FORTIN <contact@valentin-fortin.pro> */
final readonly class EquipmentMediaFailureSubscriber implements EventSubscriberInterface
{
  /**
   * @return array<string, array{string, int}> @since 1.0.0
   */
  public static function getSubscribedEvents(): array
  {
    return [KernelEvents::EXCEPTION => ['onException', 10]];
  }

  /**
   * @since 1.0.0
   */
  public function onException(ExceptionEvent $event): void
  {
    $error = $event->getThrowable();
    do {
      $status = match (true) {
        $error instanceof EquipmentMediaNotFoundException, $error instanceof EquipmentNotFoundException, $error instanceof AttachmentNotFoundException, $error instanceof InterventionNotFoundException, $error instanceof InterventionResourceNotFoundException => 404,
        $error instanceof EquipmentAccessDeniedException, $error instanceof EquipmentReportNotEntitledException, $error instanceof InterventionAccessDeniedException => 403,
        $error instanceof EquipmentMediaIdentityConflictException => 409,
        $error instanceof InterventionConflictException => 409,
        $error instanceof EquipmentRequestInvalidException => 400,
        default => null,
      };
      if (null !== $status) {
        $event->setResponse(new JsonResponse(['type' => 'about:blank', 'title' => match ($status) {
          404 => 'Not Found', 403 => 'Forbidden', 409 => 'Conflict', default => 'Bad Request'
        }, 'status' => $status, 'detail' => $error->getMessage()], $status, ['Content-Type' => 'application/problem+json']));

        return;
      }
      $error = $error->getPrevious();
    } while (null !== $error);
  }
}
