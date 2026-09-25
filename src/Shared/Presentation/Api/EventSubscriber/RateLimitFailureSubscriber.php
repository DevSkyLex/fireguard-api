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

/** Publishes rate-limit recovery data independently of translated human-readable copy. */
final readonly class RateLimitFailureSubscriber implements EventSubscriberInterface
{
  public static function getSubscribedEvents(): array
  {
    return [KernelEvents::EXCEPTION => ['onException', 10]];
  }

  public function onException(ExceptionEvent $event): void
  {
    $error = $event->getThrowable();
    do {
      if ($error instanceof HttpExceptionInterface && Response::HTTP_TOO_MANY_REQUESTS === $error->getStatusCode()) {
        $headers = $error->getHeaders();
        $rawDelay = $headers['Retry-After'] ?? $headers['retry-after'] ?? null;
        $delay = null;
        if (is_int($rawDelay)) {
          $delay = (string) $rawDelay;
        } elseif (is_string($rawDelay)) {
          $delay = $rawDelay;
        }
        $retryAfter = null;
        if (null !== $delay && ctype_digit((string) $delay)) {
          $retryAfter = (int) $delay;
        } elseif (null !== $delay && false !== ($deadline = strtotime((string) $delay))) {
          $retryAfter = max(0, $deadline - time());
        }
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
}
