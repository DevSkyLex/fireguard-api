<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\EventSubscriber;

use Facility\Domain\Exception\{FacilityHierarchyException, FacilityRevisionMismatchException};
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Subscriber FacilityHierarchyExceptionSubscriber.
 *
 * Keeps hierarchy and revision failures consistent across canonical and legacy processors.
 *
 * @category EventSubscriber
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FacilityHierarchyExceptionSubscriber implements EventSubscriberInterface
{
  // #region Methods
  /**
   * Method getSubscribedEvents.
   *
   * @since unreleased
   *
   * @return array<string, array{0: string, 1: int}> kernel exception events
   */
  public static function getSubscribedEvents(): array
  {
    return [KernelEvents::EXCEPTION => ['onException', 30]];
  }

  /**
   * Method onException.
   *
   * @since unreleased
   *
   * @param ExceptionEvent $event the wrapped mutation failure
   */
  public function onException(ExceptionEvent $event): void
  {
    $error = $event->getThrowable();
    do {
      if ($error instanceof FacilityHierarchyException || $error instanceof FacilityRevisionMismatchException) {
        $event->setThrowable(new HttpException($error instanceof FacilityHierarchyException ? 422 : 412, $error->getMessage(), $error));

        return;
      }
      $error = $error->getPrevious();
    } while (null !== $error);
  }
  // #endregion
}
