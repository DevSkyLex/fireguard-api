<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\EventSubscriber;

use ApiPlatform\Metadata\Exception\ProblemExceptionInterface;
use ApiPlatform\Metadata\Operation;
use Facility\Domain\Exception\{FacilityAccessDeniedException, FacilityAttachmentNotFoundException, FacilityCreationPreconditionFailedException, FacilityNotFoundException};
use Facility\Presentation\Api\Http\ClientResourceAlreadyExistsHttpException;
use Intervention\Application\Contract\Resource\ClientResourceAlreadyExistsException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\{HttpException, HttpExceptionInterface};
use Symfony\Component\HttpKernel\KernelEvents;

use function is_string;
use function str_starts_with;

/**
 * Subscriber FacilityAccessExceptionSubscriber.
 *
 * Maps application access and owner failures consistently across HTTP adapters.
 *
 * @category EventSubscriber
 */
final readonly class FacilityAccessExceptionSubscriber implements EventSubscriberInterface
{
  // #region Methods
  /**
   * Method getSubscribedEvents. @return array<string, array{string, int}> the subscriptions
   */
  public static function getSubscribedEvents(): array
  {
    return [KernelEvents::EXCEPTION => ['onException', 30]];
  }

  /**
   * Method onException.
   */
  public function onException(ExceptionEvent $event): void
  {
    $error = $event->getThrowable();
    if ($error instanceof HttpExceptionInterface || $error instanceof ProblemExceptionInterface) {
      return;
    }
    do {
      if ($error instanceof ClientResourceAlreadyExistsException && $this->isFacilityOperation($event)) {
        $event->setThrowable(new ClientResourceAlreadyExistsHttpException(409, $error));

        return;
      }
      $status = match (true) {
        $error instanceof FacilityAccessDeniedException => 403,
        $error instanceof FacilityNotFoundException, $error instanceof FacilityAttachmentNotFoundException => 404,
        $error instanceof FacilityCreationPreconditionFailedException => 412,
        default => null,
      };
      if (null !== $status) {
        $event->setThrowable(new HttpException($status, $error->getMessage(), $error));

        return;
      }
      $error = $error->getPrevious();
    } while (null !== $error);
  }

  /**
   * Method isFacilityOperation.
   *
   * Keeps shared client-identity failures scoped to the owning HTTP surface.
   *
   * @since 1.0.0
   *
   * @param ExceptionEvent $event the HTTP failure
   *
   * @return bool whether the API resource belongs to Facility
   */
  private function isFacilityOperation(ExceptionEvent $event): bool
  {
    $class = $event->getRequest()->attributes->get('_api_resource_class');
    if (!is_string($class)) {
      $operation = $event->getRequest()->attributes->get('_api_operation');
      $class = $operation instanceof Operation ? $operation->getClass() : null;
    }

    return is_string($class) && str_starts_with($class, 'Facility\\Presentation\\Api\\Resource\\');
  }
  // #endregion
}
