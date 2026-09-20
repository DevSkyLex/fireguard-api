<?php

declare(strict_types=1);

namespace Workload\Presentation\Api\EventSubscriber;

/**
 * WorkloadConfirmationSubscriber.
 *
 * @category Workload
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class WorkloadConfirmationSubscriber implements \Symfony\Component\EventDispatcher\EventSubscriberInterface
{
  /**
   * @since 1.0.0
   *
   * @return array<string, array{string, int}>
   */
  public static function getSubscribedEvents(): array
  {
    return [\Symfony\Component\HttpKernel\KernelEvents::EXCEPTION => ['onException', 10]];
  }

  /**
   * Translates a workload confirmation conflict into its API problem response.
   *
   * @since 1.0.0
   *
   * @param \Symfony\Component\HttpKernel\Event\ExceptionEvent $event kernel exception event to translate into a workload conflict response
   *
   * @return void completes without returning a value
   */
  public function onException(\Symfony\Component\HttpKernel\Event\ExceptionEvent $event): void
  {
    $exception = $event->getThrowable();
    if ($exception instanceof \Workload\Application\Contract\Planning\WorkloadConfirmationRequired) {
      $event->setResponse(new \Symfony\Component\HttpFoundation\JsonResponse([
        '@type' => 'Error', '@id' => '',
        'type' => '/errors/workload-confirmation-required', 'title' => 'Workload confirmation required', 'status' => 409,
        'detail' => $exception->getMessage(), 'code' => 'workload_confirmation_required', 'assessment' => $exception->assessment,
      ], 409, ['Content-Type' => 'application/problem+json']));
    }
  }
}
