<?php

declare(strict_types=1);

namespace Assistant\Presentation\Api\EventSubscriber;

use Assistant\Domain\Exception\AssistantAttemptConflictException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/** Subscriber AssistantAttemptFailureSubscriber. Stable conflict contract across command bus wrappers. */
final readonly class AssistantAttemptFailureSubscriber implements EventSubscriberInterface
{
  // #region Methods
  /**
   * Method getSubscribedEvents
   *
   * Returns the event-to-handler map used to register this subscriber.
   *
   * @access public
   *
   * @return array<string, string|array{0: string, 1: int}> the event subscription map
   */
  public static function getSubscribedEvents(): array
  {
    return [KernelEvents::EXCEPTION => ['onException', 10]];
  }

  /**
   * Method onException
   *
   * Handles an exception event and applies the subscriber response policy.
   *
   * @access public
   *
   * @param ExceptionEvent $event the event to handle
   *
   * @return void
   */
  public function onException(ExceptionEvent $event): void
  {
    $error = $event->getThrowable();
    do {
      if ($error instanceof AssistantAttemptConflictException) {
        $event->setResponse(new JsonResponse(['type' => '/errors/assistant_attempt_conflict', 'title' => 'Generation action refused', 'status' => 409, 'code' => 'assistant_attempt_conflict', 'detail' => $error->getMessage()], 409, ['Content-Type' => 'application/problem+json']));

        return;
      }
      $error = $error->getPrevious();
    } while (null !== $error);
  }
  // #endregion
}
