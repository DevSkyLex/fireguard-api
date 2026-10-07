<?php

declare(strict_types=1);

namespace MaintenanceExport\Presentation\Api\EventSubscriber;

use MaintenanceExport\Domain\Exception\MaintenanceExportException;
use Shared\Domain\Exception\InvalidValueException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\{HttpException,HttpExceptionInterface};
use Symfony\Component\HttpKernel\KernelEvents;
use Throwable;
use ValueError;

use function str_contains;

/**
 * Class MaintenanceExportExceptionSubscriber
 * Maps bus-wrapped domain failures centrally without disclosing private artifact contents.
 *
 * @category EventSubscriber
 */
final class MaintenanceExportExceptionSubscriber implements EventSubscriberInterface
{
  // #region Methods
  /**
   * Method getSubscribedEvents
   *
   * @return array<string,array{string,int}> kernel error mapping
   */
  public static function getSubscribedEvents(): array
  {
    return [KernelEvents::EXCEPTION => ['onKernelException', 10]];
  }

  /**
   * Method onKernelException
   *
   * @return void
   */
  public function onKernelException(ExceptionEvent $event): void
  {
    $original = $event->getThrowable();
    if ($original instanceof HttpExceptionInterface || !str_contains($event->getRequest()->getPathInfo(), '/maintenance-export')) {
      return;
    }
    $current = $original;
    do {
      if ($current instanceof MaintenanceExportException) {
        $status = match($current->reason) {
          'not_found' => 404,'denied' => 403,'stale' => 412,'revision_required' => 428,'conflict' => 409,default => 422
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
  // #endregion
}
