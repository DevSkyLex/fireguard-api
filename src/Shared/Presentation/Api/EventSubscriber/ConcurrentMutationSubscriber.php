<?php

declare(strict_types=1);

namespace Shared\Presentation\Api\EventSubscriber;

use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/** Subscriber ConcurrentMutationSubscriber. A stale database write is a recoverable precondition failure. */
final readonly class ConcurrentMutationSubscriber implements EventSubscriberInterface
{
  public static function getSubscribedEvents(): array
  {
    return [KernelEvents::EXCEPTION => ['onException', 10]];
  }

  public function onException(ExceptionEvent $event): void
  {
    $error = $event->getThrowable();
    do {
      if ($error instanceof OptimisticLockException) {
        $event->setResponse(new JsonResponse([
          'type' => '/errors/resource_revision_conflict', 'title' => 'Resource changed',
          'status' => 412, 'code' => 'resource_revision_conflict',
          'detail' => 'The resource changed. Refresh it before trying again.',
        ], 412, ['Content-Type' => 'application/problem+json']));

        return;
      }
      $error = $error->getPrevious();
    } while (null !== $error);
  }
}
