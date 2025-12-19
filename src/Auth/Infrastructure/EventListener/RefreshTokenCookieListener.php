<?php

declare(strict_types=1);

namespace Auth\Infrastructure\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Listener RefreshTokenCookieListener.
 *
 * @category EventListener
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[AsEventListener(event: KernelEvents::RESPONSE, method: 'onKernelResponse')]
final readonly class RefreshTokenCookieListener
{
    // #region Methods
    /**
     * Method onKernelResponse.
     *
     * Adds the refresh token cookie to the
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
        $cookie = $request->attributes->get(key: '_refresh_token_cookie');

        if ($cookie instanceof Cookie) {
            $response = $event->getResponse();
            $response->headers->setCookie(cookie: $cookie);
        }
    }
    // #endregion
}
