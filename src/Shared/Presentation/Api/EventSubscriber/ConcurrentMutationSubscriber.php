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
  /**
   * Method getSubscribedEvents
   *
   * Registers the listener that maps optimistic locking conflicts to HTTP 412 responses.
   *
   * @access public
   *
   * @static
   *
   * @return array<string, array{string, int}> exception event mapped to its listener and priority
   */
  public static function getSubscribedEvents(): array
  {
    return [KernelEvents::EXCEPTION => ['onException', 10]];
  }

  /**
   * Method onException
   *
   * Converts an optimistic locking exception into a resource revision conflict response.
   *
   * @access public
   *
   * @param ExceptionEvent $event the kernel exception event
   *
   * @return void no return value
   */
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
