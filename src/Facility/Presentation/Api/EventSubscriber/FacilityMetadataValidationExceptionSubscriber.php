<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\EventSubscriber;

use Facility\Domain\Exception\FacilityMetadataValidationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\{HttpExceptionInterface, UnprocessableEntityHttpException};
use Symfony\Component\HttpKernel\KernelEvents;
use Throwable;

/**
 * EventSubscriber FacilityMetadataValidationExceptionSubscriber.
 *
 * Central HTTP mapping for {@see FacilityMetadataValidationException},
 * raised by {@see \Facility\Application\Service\FacilityMetadataSchemaGuard}
 * from three different write paths — the CreateFacility/UpdateFacility
 * command handlers (reaching the kernel wrapped by the messenger bus), the
 * canonical PATCH processor (thrown directly), and the offline intervention
 * apply() path (rethrown as {@see \Intervention\Domain\Exception\InterventionConflictException},
 * which maps to 409 on its own path and never reaches this subscriber).
 * Mirrors {@see \Shared\Presentation\Api\EventSubscriber\AttachmentConstraintExceptionSubscriber}:
 * one status (422) for every one of these violations, regardless of which
 * path raised it, without any processor mapping it locally.
 *
 * @category EventSubscriber
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FacilityMetadataValidationExceptionSubscriber implements EventSubscriberInterface
{
  // #region Methods
  /**
   * Method getSubscribedEvents.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @return array<string, array{0: string, 1: int}> the subscribed events
   */
  public static function getSubscribedEvents(): array
  {
    return [
      KernelEvents::EXCEPTION => ['onKernelException', 10],
    ];
  }

  /**
   * Method onKernelException.
   *
   * @since 1.0.0
   *
   * @param ExceptionEvent $event the kernel exception event
   */
  public function onKernelException(ExceptionEvent $event): void
  {
    $throwable = $event->getThrowable();

    if ($throwable instanceof HttpExceptionInterface) {
      return;
    }

    $current = $throwable;

    do {
      if ($current instanceof FacilityMetadataValidationException) {
        $event->setThrowable(new UnprocessableEntityHttpException($current->getMessage(), $throwable));

        return;
      }

      $current = $current->getPrevious();
    } while ($current instanceof Throwable);
  }
  // #endregion
}
