<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\EventSubscriber;

use Facility\Domain\Exception\FacilityModelException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Subscriber FacilityModelExceptionSubscriber.
 *
 * @category EventSubscriber
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FacilityModelExceptionSubscriber implements EventSubscriberInterface
{
  // #region Methods
  /**
   * Method getSubscribedEvents.
   *
   * Declares the kernel events handled by this subscriber.
   *
   * @access public
   * @since 1.0.0
   *
   * @return array<string, array{string, int}>
   */
  public static function getSubscribedEvents(): array
  {
    return [KernelEvents::EXCEPTION => ['onException', 10]];
  }

  /**
   * Method onException.
   *
   * Maps domain model failures to stable RFC 7807 error responses.
   *
   * @access public
   * @since 1.0.0
   *
   * @param ExceptionEvent $event the event
   *
   * @return void no return value
   */
  public function onException(ExceptionEvent $event): void
  {
    $error = $event->getThrowable();
    do {
      if ($error instanceof FacilityModelException) {
        $status = match ($error->reason) {
          'model_not_found' => 404, 'model_access_denied' => 403,
          'model_revision_stale' => 412, 'model_limit_reached' => 409,
          default => 422,
        };
        $event->setResponse(new JsonResponse([
          'type' => '/errors/' . $error->reason, 'title' => 'Facility model request refused',
          'status' => $status, 'code' => $error->reason, 'detail' => $error->getMessage(),
        ], $status, ['Content-Type' => 'application/problem+json']));

        return;
      }
      $error = $error->getPrevious();
    } while (null !== $error);
  }
  // #endregion
}
