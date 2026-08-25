<?php

declare(strict_types=1);

namespace Shared\Presentation\Api\EventSubscriber;

use Shared\Application\Exception\MessengerRuntimeException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Throwable;

use function array_key_first;

/**
 * EventSubscriber BusFailureUnwrappingSubscriber.
 *
 * Replaces a bus-wrapped failure with the domain exception inside it, once, for
 * everyone.
 *
 * `MessengerCommandBusAdapter::dispatch()` catches `Throwable` and wraps it in
 * `MessengerRuntimeException`, and Symfony Messenger has already wrapped the
 * handler's throw in `HandlerFailedException`. **Every domain exception raised
 * by a handler therefore reaches the kernel wrapped twice**, and nothing that
 * inspects the exception by class can see it.
 *
 * That single fact is why 258 Presentation files hand-roll the unwrapping, why
 * `UnwrapsOrganizationBusFailures` exists, and why `api_platform.exception_to_status`
 * — already configured, already working — could only ever cover the three
 * exceptions that happen to be thrown *before* the dispatch.
 *
 * Unwrapping centrally is what makes declarative mapping possible at all. This
 * subscriber is step 1 of FG-035 and does nothing else: it never decides a
 * status, and it never touches an exception that is not a bus wrapper.
 *
 * Runs at priority 20, ahead of the three subscribers that sit at 10
 * ({@see AttachmentConstraintExceptionSubscriber}, `FacilityMetadataValidationExceptionSubscriber`,
 * `OAuthErrorSubscriber`). Two of them walk `getPrevious()` themselves, so they
 * keep working either way; running first simply means they see the real
 * exception rather than its envelope.
 *
 * @category EventSubscriber
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class BusFailureUnwrappingSubscriber implements EventSubscriberInterface
{
  // #region Constants
  /**
   * Ahead of the module subscribers at 10, and far ahead of Symfony's security
   * `ExceptionListener` at 2.
   *
   * @since 1.0.0
   */
  public const int PRIORITY = 20;
  // #endregion

  // #region Methods
  /**
   * Method getSubscribedEvents.
   *
   * @since 1.0.0
   *
   * @return array<string, array{0: string, 1: int}> the subscribed events
   */
  public static function getSubscribedEvents(): array
  {
    return [
      KernelEvents::EXCEPTION => ['onKernelException', self::PRIORITY],
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

    // A processor that already mapped the failure has spoken. Re-deciding here
    // would silently override every explicit mapping still in place, which is
    // exactly what this step must not do while the two mechanisms coexist.
    if ($throwable instanceof HttpExceptionInterface) {
      return;
    }

    $unwrapped = self::unwrap($throwable);

    if (null === $unwrapped || $unwrapped === $throwable) {
      return;
    }

    $event->setThrowable($unwrapped);
  }

  /**
   * Method unwrap.
   *
   * Walks the envelopes down to the first exception that is not one.
   *
   * `HandlerFailedException::getWrappedExceptions()` is preferred over
   * `getPrevious()` where available: a message handled by several handlers
   * carries several failures, and the previous chain exposes only one of them.
   * The first is taken — this repository dispatches one handler per message, so
   * a second would itself be the anomaly.
   *
   * @since 1.0.0
   *
   * @param Throwable $throwable the exception as thrown
   *
   * @return Throwable|null the innermost domain exception, or null when there was no envelope
   */
  private static function unwrap(Throwable $throwable): ?Throwable
  {
    $current = $throwable;
    $unwrapped = null;

    while (self::isEnvelope($current)) {
      $next = self::peel($current);

      if (null === $next) {
        break;
      }

      $unwrapped = $next;
      $current = $next;
    }

    return $unwrapped;
  }

  /**
   * Method isEnvelope.
   *
   * @since 1.0.0
   *
   * @param Throwable $throwable the exception to classify
   *
   * @return bool true when the exception only carries another one
   */
  private static function isEnvelope(Throwable $throwable): bool
  {
    return $throwable instanceof MessengerRuntimeException
      || $throwable instanceof HandlerFailedException;
  }

  /**
   * Method peel.
   *
   * @since 1.0.0
   *
   * @param Throwable $envelope the envelope to open
   *
   * @return Throwable|null what it carries, or null when it carries nothing
   */
  private static function peel(Throwable $envelope): ?Throwable
  {
    if ($envelope instanceof HandlerFailedException) {
      $wrapped = $envelope->getWrappedExceptions();

      if ([] !== $wrapped) {
        return $wrapped[array_key_first($wrapped)];
      }
    }

    return $envelope->getPrevious();
  }
  // #endregion
}
