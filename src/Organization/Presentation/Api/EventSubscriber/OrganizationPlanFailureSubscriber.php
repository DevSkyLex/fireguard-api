<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\EventSubscriber;

use InvalidArgumentException;
use Organization\Application\Contract\Exception\{OrganizationAccessDeniedException, OrganizationNotFoundException};
use Organization\Domain\Exception\{OrganizationPlanUsageExceededException, PaidOrganizationPlanRequiresBillingException, PlanNotAvailableException, PlanNotFoundException};
use Shared\Domain\Exception\InvalidValueException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException, HttpExceptionInterface, NotFoundHttpException};
use Symfony\Component\HttpKernel\KernelEvents;

use function preg_match;

/**
 * Subscriber OrganizationPlanFailureSubscriber.
 *
 * Maps unwrapped use-case failures after the shared bus subscriber.
 *
 * @category Subscriber
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationPlanFailureSubscriber implements EventSubscriberInterface
{
  // #region Methods
  /**
   * Method getSubscribedEvents.
   *
   * @since 1.0.0
   *
   * @return array<string, array{string, int}> the kernel exception mapping
   */
  public static function getSubscribedEvents(): array
  {
    return [KernelEvents::EXCEPTION => ['onException', 10]];
  }

  /**
   * Method onException.
   *
   * @since 1.0.0
   *
   * @param ExceptionEvent $event the request and unwrapped failure
   */
  public function onException(ExceptionEvent $event): void
  {
    if (1 !== preg_match('#^/api/organizations/[^/]+/plan$#', $event->getRequest()->getPathInfo())) {
      return;
    }
    $failure = $event->getThrowable();
    if ($failure instanceof HttpExceptionInterface) {
      return;
    }
    $mapped = match (true) {
      $failure instanceof OrganizationAccessDeniedException => new AccessDeniedHttpException($failure->getMessage(), $failure),
      $failure instanceof OrganizationNotFoundException, $failure instanceof PlanNotFoundException => new NotFoundHttpException($failure->getMessage(), $failure),
      $failure instanceof OrganizationPlanUsageExceededException, $failure instanceof PaidOrganizationPlanRequiresBillingException => new ConflictHttpException($failure->getMessage(), $failure),
      $failure instanceof InvalidArgumentException, $failure instanceof InvalidValueException, $failure instanceof PlanNotAvailableException => new BadRequestHttpException($failure->getMessage(), $failure),
      default => null,
    };
    if (null !== $mapped) {
      $event->setThrowable($mapped);
    }
  }
  // #endregion
}
