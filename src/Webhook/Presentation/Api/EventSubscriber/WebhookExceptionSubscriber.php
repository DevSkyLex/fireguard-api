<?php

declare(strict_types=1);

namespace Webhook\Presentation\Api\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Webhook\Presentation\Api\Trait\WebhookExceptionMapperTrait;

use function is_string;
use function str_starts_with;

/**
 * Class WebhookExceptionSubscriber
 *
 * Centralizes the existing status/message contract only on Webhook resources.
 *
 * @category EventSubscriber
 */
final readonly class WebhookExceptionSubscriber implements EventSubscriberInterface
{
  use WebhookExceptionMapperTrait;

  /**
   * Method getSubscribedEvents
   *
   * @access public
   *
   * @return array<string, array{string, int}>
   */
  public static function getSubscribedEvents(): array
  {
    return [KernelEvents::EXCEPTION => ['onKernelException', 10]];
  }

  /**
   * Method onKernelException
   *
   * @access public
   *
   * @param ExceptionEvent $event
   *
   * @return void
   */
  public function onKernelException(ExceptionEvent $event): void
  {
    $resource = $event->getRequest()->attributes->get('_api_resource_class');

    if (!is_string($resource) || !str_starts_with($resource, 'Webhook\\Presentation\\Api\\Resource\\')) {
      return;
    }
    $exception = $event->getThrowable();

    if (!$exception instanceof HttpExceptionInterface) {
      $event->setThrowable($this->mapWebhookException($exception));
    }
  }
}
