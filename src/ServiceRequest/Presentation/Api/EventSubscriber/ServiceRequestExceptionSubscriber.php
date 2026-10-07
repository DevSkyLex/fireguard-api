<?php

declare(strict_types=1);

namespace ServiceRequest\Presentation\Api\EventSubscriber;

use ServiceRequest\Application\Contract\Source\ServiceRequestOriginUnavailable;
use ServiceRequest\Domain\Exception\ServiceRequestException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/** Class ServiceRequestExceptionSubscriber. Centralizes workflow refusals as RFC7807 errors. @category Subscriber */
final readonly class ServiceRequestExceptionSubscriber implements EventSubscriberInterface
{
  /**
   * @return array<string,array{string,int}>
   */
  public static function getSubscribedEvents(): array
  {
    return [KernelEvents::EXCEPTION => ['onException', 10]];
  }

  public function onException(ExceptionEvent $event): void
  {
    $error = $event->getThrowable();
    do {
      if ($error instanceof ServiceRequestException || $error instanceof ServiceRequestOriginUnavailable) {
        $code = $error instanceof ServiceRequestException ? $error->reason : 'service_request_origin_unavailable';
        $status = match ($code) {
          'service_request_not_found' => 404, 'service_request_access_denied' => 403, 'service_request_revision_stale' => 412, 'service_request_precondition_required' => 428, 'service_request_transition_conflict', 'service_request_operation_conflict' => 409, default => 422
        };
        $event->setResponse(new JsonResponse(['type' => '/errors/' . $code, 'title' => 'Repair request refused', 'status' => $status, 'code' => $code, 'detail' => $error->getMessage()], $status, ['Content-Type' => 'application/problem+json']));

        return;
      }
      $error = $error->getPrevious();
    } while (null !== $error);
  }
}
