<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\EventSubscriber;

use Equipment\Domain\Exception\EquipmentAssetCodeAlreadyExistsException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Maps contextual patrimonial identity collisions, including wrapped failures.
 *
 * @category Subscriber
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class EquipmentIdentityFailureSubscriber implements EventSubscriberInterface
{
  /**
   * @since 1.0.0 @return array<string, array{string, int}>
   */
  public static function getSubscribedEvents(): array
  {
    return [KernelEvents::EXCEPTION => ['onException', 15]];
  }

  /**
   * @since 1.0.0
   */
  public function onException(ExceptionEvent $event): void
  {
    $error = $event->getThrowable();
    do {
      if ($error instanceof EquipmentAssetCodeAlreadyExistsException) {
        $event->setResponse(new JsonResponse([
          'type' => '/errors/equipment_asset_code_conflict', 'title' => 'Asset code already used',
          'status' => 409, 'code' => 'equipment_asset_code_conflict', 'detail' => $error->getMessage(),
        ], 409, ['Content-Type' => 'application/problem+json']));

        return;
      }
      $error = $error->getPrevious();
    } while (null !== $error);
  }
}
