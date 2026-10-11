<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Support;

use Equipment\Presentation\Api\EventSubscriber\EquipmentMediaFailureSubscriber;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException, NotFoundHttpException};
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Throwable;

use function json_decode;

use const JSON_THROW_ON_ERROR;

/** Exercises the centralized HTTP mapping in composed boundary tests. */
trait EquipmentHttpFailures
{
  private function httpFailure(Throwable $failure): Throwable
  {
    $event = new ExceptionEvent($this->createStub(HttpKernelInterface::class), new Request(), HttpKernelInterface::MAIN_REQUEST, $failure);
    new EquipmentMediaFailureSubscriber()->onException($event);
    $response = $event->getResponse();
    if (null === $response) {
      return $failure;
    }
    $payload = json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);

    self::assertIsArray($payload);
    self::assertArrayHasKey('detail', $payload);
    self::assertIsString($payload['detail']);

    return match ($response->getStatusCode()) {
      404 => new NotFoundHttpException($payload['detail'], $failure),
      403 => new AccessDeniedHttpException($payload['detail'], $failure),
      409 => new ConflictHttpException($payload['detail'], $failure),
      400 => new BadRequestHttpException($payload['detail'], $failure),
      default => $failure,
    };
  }
}
