<?php

declare(strict_types=1);

namespace Shared\Presentation\Api\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\{JsonResponse, Response};
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

use function ctype_digit;
use function is_int;
use function is_string;
use function max;
use function strtotime;
use function time;

/**
 * Class RateLimitFailureSubscriber
 *
 * Publishes rate-limit recovery data independently of translated human-readable copy.
 *
 * @category EventSubscriber
 */
final readonly class RateLimitFailureSubscriber implements EventSubscriberInterface
{
  // #region Methods
  /**
   * Method getSubscribedEvents
   *
   * Registers the exception listener before lower-priority HTTP error handlers.
   *
   * @access public
   *
   * @return array<string, array{string, int}> exception event mapped to its listener and priority
   */
  public static function getSubscribedEvents(): array
  {
    return [KernelEvents::EXCEPTION => ['onException', 10]];
  }

  /**
   * Method onException
   *
   * Finds a wrapped HTTP 429 and emits a problem response while retaining its retry headers.
   * Other exceptions leave the response unchanged.
   *
   * @access public
   *
   * @param ExceptionEvent $event kernel failure whose causal chain may contain a rate-limit error
   *
   * @return void
   */
  public function onException(ExceptionEvent $event): void
  {
    $error = $event->getThrowable();
    do {
      if ($error instanceof HttpExceptionInterface && Response::HTTP_TOO_MANY_REQUESTS === $error->getStatusCode()) {
        $headers = $error->getHeaders();
        $retryAfter = self::retryAfterSeconds($headers['Retry-After'] ?? $headers['retry-after'] ?? null);
        $event->setResponse(new JsonResponse([
          'type' => '/errors/rate_limit_exceeded',
          'title' => 'Too Many Requests',
          'status' => Response::HTTP_TOO_MANY_REQUESTS,
          'code' => 'rate_limit_exceeded',
          'detail' => $error->getMessage(),
          'retryAfterSeconds' => $retryAfter,
        ], Response::HTTP_TOO_MANY_REQUESTS, [
          ...$headers,
          'Content-Type' => 'application/problem+json',
        ]));

        return;
      }
      $error = $error->getPrevious();
    } while (null !== $error);
  }

  /**
   * Method retryAfterSeconds
   *
   * Converts integer seconds or a parseable date into a delay, clamping expired dates to zero.
   *
   * @access private
   *
   * @param mixed $rawDelay retry header value; unsupported types and unparseable dates yield null
   *
   * @return ?int seconds before retrying, or null when the deadline is unknown
   */
  private static function retryAfterSeconds(mixed $rawDelay): ?int
  {
    if (is_int($rawDelay)) {
      $rawDelay = (string) $rawDelay;
    }
    if (!is_string($rawDelay)) {
      return null;
    }
    if (ctype_digit($rawDelay)) {
      return (int) $rawDelay;
    }

    $deadline = strtotime($rawDelay);

    return false === $deadline ? null : max(0, $deadline - time());
  }
  // #endregion
}
