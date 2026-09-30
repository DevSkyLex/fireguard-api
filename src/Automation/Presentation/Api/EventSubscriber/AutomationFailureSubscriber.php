<?php

declare(strict_types=1);

namespace Automation\Presentation\Api\EventSubscriber;

use Automation\Domain\Exception\{AutomationRetryNotAllowedException, AutomationRunNotFoundException};
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/** Subscriber AutomationFailureSubscriber. Safe stable automation errors. */
final readonly class AutomationFailureSubscriber implements EventSubscriberInterface
{
  /**
   * Method getSubscribedEvents.
   *
   * Registers the listener that maps automation failures to stable API errors.
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
   * Method onException.
   *
   * Maps recognized automation retry failures to their public error response.
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
      if ($error instanceof AutomationRetryNotAllowedException || $error instanceof AutomationRunNotFoundException) {
        $status = $error instanceof AutomationRunNotFoundException ? 404 : 409;
        $code = 404 === $status ? 'automation_run_not_found' : 'automation_retry_conflict';
        $event->setResponse(new JsonResponse(['type' => '/errors/' . $code, 'title' => 'Automation action refused', 'status' => $status, 'code' => $code, 'detail' => $error->getMessage()], $status, ['Content-Type' => 'application/problem+json']));

        return;
      }
      $error = $error->getPrevious();
    } while (null !== $error);
  }
}
