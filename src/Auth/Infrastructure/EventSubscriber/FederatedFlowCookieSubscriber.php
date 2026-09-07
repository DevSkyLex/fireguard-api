<?php

declare(strict_types=1);

namespace Auth\Infrastructure\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Subscriber FederatedFlowCookieSubscriber.
 *
 * @category EventSubscriber
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FederatedFlowCookieSubscriber implements EventSubscriberInterface
{
  /**
   * @since 1.0.0
   */
  public function onKernelResponse(ResponseEvent $event): void
  {
    foreach (['_federated_flow_cookie', '_federated_flow_clear_cookie'] as $attribute) {
      $cookie = $event->getRequest()->attributes->get($attribute);
      if ($cookie instanceof Cookie) {
        $event->getResponse()->headers->setCookie($cookie);
      }
    }
  }

  /**
   * @return array<string, string>
   */
  public static function getSubscribedEvents(): array
  {
    return [KernelEvents::RESPONSE => 'onKernelResponse'];
  }
}
