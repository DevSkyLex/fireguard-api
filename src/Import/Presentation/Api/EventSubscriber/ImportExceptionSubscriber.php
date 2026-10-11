<?php

declare(strict_types=1);

namespace Import\Presentation\Api\EventSubscriber;

use ApiPlatform\Metadata\Operation;
use Import\Presentation\Api\Trait\ImportExceptionMapperTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

use function is_string;
use function str_starts_with;

/**
 * Class ImportExceptionSubscriber
 *
 * Maps owner HTTP failures centrally after stable-code subscribers and before API Platform normalization.
 *
 * @category EventSubscriber
 */
final readonly class ImportExceptionSubscriber implements EventSubscriberInterface
{
  use ImportExceptionMapperTrait;

  // #region Methods
  /**
   * Method getSubscribedEvents
   *
   * @access public
   *
   * @return array<string, array{string, int}> kernel exception callback and priority
   */
  public static function getSubscribedEvents(): array
  {
    return [KernelEvents::EXCEPTION => ['onKernelException', 9]];
  }

  /**
   * Method onKernelException
   *
   * Scope prevents shared authorization and argument errors from being mapped by another module.
   *
   * @access public
   *
   * @param ExceptionEvent $event current HTTP failure and server-resolved operation
   *
   * @return void
   */
  public function onKernelException(ExceptionEvent $event): void
  {
    $failure = $event->getThrowable();
    if ($failure instanceof HttpExceptionInterface) {
      return;
    }
    $request = $event->getRequest();
    $operation = $request->attributes->get('_api_operation');
    $resourceClass = $request->attributes->get('_api_resource_class');
    if (!is_string($resourceClass) && $operation instanceof Operation) {
      $resourceClass = $operation->getClass();
    }
    if (!is_string($resourceClass) || !str_starts_with($resourceClass, 'Import\\Presentation\\')) {
      return;
    }
    $mapped = $this->mapImportException($failure);
    if ($mapped !== $failure) {
      $event->setThrowable($mapped);
    }
  }
  // #endregion
}
