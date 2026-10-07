<?php

declare(strict_types=1);

namespace Procurement\Presentation\Api\EventSubscriber;

use Procurement\Domain\Exception\ProcurementException;
use Shared\Domain\Exception\InvalidValueException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\{HttpException, HttpExceptionInterface};
use Symfony\Component\HttpKernel\KernelEvents;
use Throwable;
use ValueError;

use function str_contains;

/** Central problem mapping includes bus-wrapped failures without disclosing financial data. */
final class ProcurementExceptionSubscriber implements EventSubscriberInterface
{
  public static function getSubscribedEvents(): array
  {
    return [KernelEvents::EXCEPTION => ['onKernelException', 10]];
  }

  public function onKernelException(ExceptionEvent $event): void
  {
    $original = $event->getThrowable();
    if ($original instanceof HttpExceptionInterface || !str_contains($event->getRequest()->getPathInfo(), '/procurement/')) {
      return;
    }
    $current = $original;
    do {
      if ($current instanceof ProcurementException) {
        $status = match ($current->errorCode) {
          'not_found' => 404, 'denied' => 403, 'stale' => 412, 'revision_required' => 428, 'conflict' => 409, default => 422,
        };
        $event->setThrowable(new HttpException($status, $current->getMessage(), $original));

        return;
      }
      if ($current instanceof InvalidValueException || $current instanceof ValueError) {
        $event->setThrowable(new HttpException(422, $current->getMessage(), $original));

        return;
      }
      $current = $current->getPrevious();
    } while ($current instanceof Throwable);
  }
}
