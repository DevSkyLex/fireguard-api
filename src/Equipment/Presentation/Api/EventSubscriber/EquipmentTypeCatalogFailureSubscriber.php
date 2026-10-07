<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\EventSubscriber;

use Equipment\Domain\Exception\EquipmentTypeCatalogException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Subscriber EquipmentTypeCatalogFailureSubscriber.
 *
 * Maps catalog failures consistently, including use-case bus wrappers.
 *
 * @category EventSubscriber
 */
final readonly class EquipmentTypeCatalogFailureSubscriber implements EventSubscriberInterface
{
  // #region Methods
  /**
   * Method getSubscribedEvents.
   *
   * @access public
   *
   * @return array<string, array{string, int}> listeners
   */
  public static function getSubscribedEvents(): array
  {
    return [KernelEvents::EXCEPTION => ['onException', 10]];
  }

  /**
   * Method onException.
   *
   * @access public
   *
   * @param ExceptionEvent $event kernel exception event
   *
   * @return void
   */
  public function onException(ExceptionEvent $event): void
  {
    $error = $event->getThrowable();
    do {
      if ($error instanceof EquipmentTypeCatalogException) {
        $status = match ($error->reason) {
          'equipment_type_not_found' => 404,
          'equipment_type_exists' => 409,
          'equipment_type_revision_conflict' => 412,
          default => 422,
        };
        $event->setResponse(new JsonResponse([
          'type' => '/errors/' . $error->reason,
          'title' => 'Equipment type change refused',
          'status' => $status,
          'code' => $error->reason,
          'detail' => $error->getMessage(),
        ], $status, ['Content-Type' => 'application/problem+json']));

        return;
      }
      $error = $error->getPrevious();
    } while (null !== $error);
  }
  // #endregion
}
