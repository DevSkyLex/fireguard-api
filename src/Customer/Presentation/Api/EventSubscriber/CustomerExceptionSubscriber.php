<?php

declare(strict_types=1);

namespace Customer\Presentation\Api\EventSubscriber;

use Customer\Domain\Exception\CustomerException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/** Class CustomerExceptionSubscriber. Centralized stable RFC7807 customer error mapping. @category Subscriber */
final readonly class CustomerExceptionSubscriber implements EventSubscriberInterface
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
      if ($error instanceof CustomerException) {
        $status = match ($error->reason) {
          'customer_not_found' => 404, 'customer_access_denied' => 403, 'customer_revision_stale' => 412, 'customer_precondition_required' => 428, 'customer_code_conflict' => 409, default => 422
        };
        $event->setResponse(new JsonResponse(['type' => '/errors/' . $error->reason, 'title' => 'Customer request refused', 'status' => $status, 'code' => $error->reason, 'detail' => $error->getMessage()], $status, ['Content-Type' => 'application/problem+json']));

        return;
      }
      $error = $error->getPrevious();
    } while (null !== $error);
  }
}
