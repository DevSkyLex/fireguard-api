<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\EventSubscriber;

use Facility\Application\Contract\FacilityCustomerUnavailable;
use Facility\Domain\Exception\{FacilityCustomerAssignmentException, FacilityCustomerScopeNotFoundException};
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/** Class FacilityCustomerExceptionSubscriber. Centralizes contextual customer assignment refusals. @category Subscriber */
final readonly class FacilityCustomerExceptionSubscriber implements EventSubscriberInterface
{
  /**
   * @return array<string,array{string,int}>
   */
  public static function getSubscribedEvents(): array
  {
    return [KernelEvents::EXCEPTION => ['onException', 15]];
  }

  public function onException(ExceptionEvent $event): void
  {
    $error = $event->getThrowable();
    do {
      if ($error instanceof FacilityCustomerScopeNotFoundException) {
        $event->setResponse(new JsonResponse(['type' => '/errors/facility_customer_not_found', 'title' => 'Customer not found', 'status' => 404, 'code' => 'facility_customer_not_found', 'detail' => $error->getMessage()], 404, ['Content-Type' => 'application/problem+json']));

        return;
      }
      if ($error instanceof FacilityCustomerUnavailable || $error instanceof FacilityCustomerAssignmentException) {
        $event->setResponse(new JsonResponse(['type' => '/errors/facility_customer_invalid', 'title' => 'Facility customer assignment refused', 'status' => 422, 'code' => 'facility_customer_invalid', 'detail' => $error->getMessage()], 422, ['Content-Type' => 'application/problem+json']));

        return;
      }
      $error = $error->getPrevious();
    } while (null !== $error);
  }
}
