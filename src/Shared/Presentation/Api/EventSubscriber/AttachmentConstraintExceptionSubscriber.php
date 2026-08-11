<?php

declare(strict_types=1);

namespace Shared\Presentation\Api\EventSubscriber;

use Shared\Domain\Attachment\InvalidAttachmentException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\{HttpExceptionInterface, UnprocessableEntityHttpException};
use Symfony\Component\HttpKernel\KernelEvents;
use Throwable;

/**
 * EventSubscriber AttachmentConstraintExceptionSubscriber.
 *
 * Central HTTP mapping for {@see InvalidAttachmentException} raised inside
 * a use-case handler — today the per-parent attachment count cap of
 * {@see \Shared\Domain\Attachment\AttachmentConstraints::validateCount()}.
 * Replaces the throwable with a `422 Unprocessable Entity`, the same status
 * {@see \Shared\Presentation\Api\Attachment\MultipartAttachmentGuard}
 * already returns for MIME-type and size rejections, so every
 * attachment-policy violation shares one status without any module's
 * processor mapping it locally.
 *
 * The exception reaches the kernel wrapped by the messenger bus
 * (`MessengerRuntimeException` → `HandlerFailedException` → …), hence the
 * walk down the `getPrevious()` chain. Runs at priority 10 — before
 * API Platform's own exception listener — mirroring `OAuthErrorSubscriber`.
 * An already-mapped `HttpExceptionInterface` (e.g. the guard's own 422) is
 * left untouched.
 *
 * @category EventSubscriber
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class AttachmentConstraintExceptionSubscriber implements EventSubscriberInterface
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
   * Rewrites a bus-wrapped attachment-policy violation into the HTTP 422
   * API Platform then normalizes to RFC 7807.
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
      if ($current instanceof InvalidAttachmentException) {
        $event->setThrowable(new UnprocessableEntityHttpException($current->getMessage(), $throwable));

        return;
      }

      $current = $current->getPrevious();
    } while ($current instanceof Throwable);
  }
  // #endregion
}
