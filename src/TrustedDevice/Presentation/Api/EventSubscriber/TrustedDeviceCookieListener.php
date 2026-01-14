<?php

declare(strict_types=1);

namespace TrustedDevice\Presentation\Api\EventSubscriber;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Listener TrustedDeviceCookieListener.
 *
 * @category EventListener
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[AsEventListener(event: KernelEvents::RESPONSE, method: 'onKernelResponse')]
final readonly class TrustedDeviceCookieListener
{
  // #region Constants
  public const string REQUEST_ATTRIBUTE = '_trusted_device_cookie';
  // #endregion

  // #region Methods
  /**
   * Method onKernelResponse.
   *
   * Adds the trusted device cookie to the
   * response if set by a processor.
   *
   * @since 1.0.0
   *
   * @param ResponseEvent $event The response event
   *
   * @return void No return value
   */
  public function onKernelResponse(ResponseEvent $event): void
  {
    $request = $event->getRequest();
    $cookie = $request->attributes->get(key: self::REQUEST_ATTRIBUTE);

    if ($cookie instanceof Cookie) {
      $response = $event->getResponse();
      $response->headers->setCookie(cookie: $cookie);
    }
  }
  // #endregion
}
