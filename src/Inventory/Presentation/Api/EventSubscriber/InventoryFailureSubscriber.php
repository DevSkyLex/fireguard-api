<?php

declare(strict_types=1);

namespace Inventory\Presentation\Api\EventSubscriber;

use InvalidArgumentException;
use Inventory\Application\Contract\Stock\InventoryPublicationBlockedException;
use Inventory\Domain\Exception\{InventoryConflictException,InventoryNotFoundException};
use Shared\Domain\Exception\InvalidValueException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

use function is_string;
use function str_starts_with;

/** Central stock failure mapping, including stock ports called by procurement. @category EventSubscriber */
final readonly class InventoryFailureSubscriber implements EventSubscriberInterface
{
  /**
   * @return array<string,array{string,int}>
   */
  public static function getSubscribedEvents(): array
  {
    return [KernelEvents::EXCEPTION => ['onException', 12]];
  }

  public function onException(ExceptionEvent $event): void
  {
    $operation = $event->getRequest()->attributes->get('_api_operation_name', '');
    $inventory = is_string($operation) && str_starts_with($operation, 'inventory_');
    $error = $event->getThrowable();
    do {
      $status = match(true) {
        $error instanceof InventoryNotFoundException => 404,$error instanceof InventoryConflictException || $error instanceof InventoryPublicationBlockedException => 409,$inventory && ($error instanceof InvalidArgumentException || $error instanceof InvalidValueException) => 422,default => null
      };
      if (null !== $status) {
        $code = match($status) {
          404 => 'inventory_not_found',409 => 'inventory_conflict',default => 'inventory_invalid'
        };
        $event->setResponse(new JsonResponse(['type' => '/errors/' . $code, 'title' => 'Inventory operation refused', 'status' => $status, 'code' => $code, 'detail' => $error->getMessage()], $status, ['Content-Type' => 'application/problem+json']));

        return;
      }
      $error = $error->getPrevious();
    } while (null !== $error);
  }
}
