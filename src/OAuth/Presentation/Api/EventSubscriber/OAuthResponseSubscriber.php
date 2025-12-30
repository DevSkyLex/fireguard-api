<?php

declare(strict_types=1);

namespace OAuth\Presentation\Api\EventSubscriber;

use OAuth\Presentation\Api\Operation\OAuthOperations;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

use function in_array;
use function is_string;

/**
 * Subscriber OAuthResponseSubscriber.
 *
 * Adds OAuth2 cache-control headers on token operations.
 *
 * @category Event Subscriber
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OAuthResponseSubscriber implements EventSubscriberInterface
{
  // #region Constants
  /**
   * Constant CACHE_HEADERS.
   *
   * Standard OAuth2 cache-control headers.
   *
   * @since 1.0.0
   *
   * @var array<string, string>
   */
  private const array CACHE_HEADERS = [
    'Cache-Control' => 'no-store',
    'Pragma' => 'no-cache',
  ];
  // #endregion

  // #region Methods
  /**
   * Method getSubscribedEvents.
   *
   * Returns the events to subscribe to.
   *
   * @since 1.0.0
   *
   * @return array<string, array{0: string, 1: int}>
   */
  public static function getSubscribedEvents(): array
  {
    return [
      KernelEvents::RESPONSE => ['onKernelResponse', 0],
    ];
  }

  /**
   * Method onKernelResponse.
   *
   * Adds OAuth2 cache headers for token operations.
   *
   * @since 1.0.0
   *
   * @param ResponseEvent $event the response event
   *
   * @return void no return value
   */
  public function onKernelResponse(ResponseEvent $event): void
  {
    $operationName = $event->getRequest()->attributes->get('_api_operation_name');

    if (!is_string($operationName) || !in_array($operationName, OAuthOperations::TOKEN_OPERATIONS, true)) {
      return;
    }

    $response = $event->getResponse();
    foreach (self::CACHE_HEADERS as $header => $value) {
      $response->headers->set($header, $value);
    }
  }
  // #endregion
}
