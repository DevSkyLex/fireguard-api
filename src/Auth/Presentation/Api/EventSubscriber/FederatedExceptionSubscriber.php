<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\EventSubscriber;

use Auth\Domain\Exception\Federation\{FederatedAuthException, FederatedConflictException, FederatedUnauthorizedException};
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\{BadRequestHttpException, ConflictHttpException, UnauthorizedHttpException};

/**
 * Maps federation failures after unwrapping the command/query bus boundary.
 *
 * @category EventSubscriber
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FederatedExceptionSubscriber implements EventSubscriberInterface
{
  public static function getSubscribedEvents(): array
  {
    return ['kernel.exception' => ['onException', 120]];
  }

  public function onException(ExceptionEvent $event): void
  {
    $exception = $event->getThrowable();
    while (null !== $exception) {
      if ($exception instanceof FederatedAuthException) {
        $mapped = match (true) {
          $exception instanceof FederatedConflictException => new ConflictHttpException($exception->errorCode, $exception),
          $exception instanceof FederatedUnauthorizedException => new UnauthorizedHttpException('Bearer', $exception->errorCode, $exception),
          default => new BadRequestHttpException($exception->errorCode, $exception),
        };
        $event->setThrowable($mapped);

        return;
      }
      $exception = $exception->getPrevious();
    }
  }
}
