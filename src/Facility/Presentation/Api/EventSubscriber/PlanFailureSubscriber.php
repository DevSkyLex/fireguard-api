<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/** Subscriber PlanFailureSubscriber. Stable recovery codes without exposing hidden resources. */
final readonly class PlanFailureSubscriber implements EventSubscriberInterface
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
        $error instanceof \Facility\Domain\Exception\FacilityAttachmentNotAncestorException => 'floor_plan_outside_ancestry',
        $error instanceof \Facility\Domain\Exception\FacilityAttachmentNotFloorPlanException => 'attachment_not_floor_plan',
        default => null,
      };
      if (null !== $code) {
        $event->setResponse(new JsonResponse([
          'type' => '/errors/' . $code, 'title' => 'Plan change refused',
          'status' => 409, 'code' => $code, 'detail' => $error->getMessage(),
        ], 409, ['Content-Type' => 'application/problem+json']));

        return;
      }
      $error = $error->getPrevious();
    } while (null !== $error);
  }
}
