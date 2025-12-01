<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * EventSubscriber RefreshTokenCookieSubscriber
 * @final
 *
 * Adds the refresh token cookie to the response if set by a processor.
 *
 * @category EventSubscriber
 * @package Auth\Presentation\Api\EventSubscriber
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class RefreshTokenCookieSubscriber implements EventSubscriberInterface
{
  /**
   * @inheritDoc
   */
  public static function getSubscribedEvents(): array
  {
    return [
      KernelEvents::RESPONSE => ['onKernelResponse', 0],
    ];
  }

  /**
   * Add refresh token cookie to response if present in request attributes.
   */
  public function onKernelResponse(ResponseEvent $event): void
  {
    $request = $event->getRequest();
    $cookie = $request->attributes->get('_refresh_token_cookie');

    if ($cookie instanceof Cookie) {
      $response = $event->getResponse();
      $response->headers->setCookie($cookie);
    }
  }
}
