<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\EventSubscriber;

use Intervention\Application\Contract\Resource\InterventionDraftDependencyConflict;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Subscriber InterventionDraftDependencySubscriber.
 *
 * @category EventSubscriber
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionDraftDependencySubscriber implements EventSubscriberInterface
{
  // #region Methods
  /**
   * @since 1.0.0
   *
   * @return array<string, array{string, int}> the exception listener
   */
  public static function getSubscribedEvents(): array
  {
    return [KernelEvents::EXCEPTION => ['onException', 10]];
  }

  /**
   * Maps retained dependencies without revealing protected resource identifiers.
   *
   * @since 1.0.0
   *
   * @param ExceptionEvent $event the kernel exception event
   */
  public function onException(ExceptionEvent $event): void
  {
    $error = $event->getThrowable();
    do {
      if ($error instanceof InterventionDraftDependencyConflict) {
        $dependencies = [];
        foreach ($error->references as $reference) {
          $type = $reference['resourceType'];
          $dependencies[$type] = ($dependencies[$type] ?? 0) + 1;
        }
        $event->setResponse(new JsonResponse([
          'type' => '/errors/intervention_draft_dependencies', 'title' => 'Draft resources are still in use',
          'status' => 409, 'code' => 'intervention_draft_dependencies', 'detail' => $error->getMessage(),
          'dependencies' => $dependencies,
        ], 409, ['Content-Type' => 'application/problem+json']));

        return;
      }
      $error = $error->getPrevious();
    } while (null !== $error);
  }
  // #endregion
}
