<?php

declare(strict_types=1);

namespace MaintenanceCost\Presentation\Api\EventSubscriber;

use MaintenanceCost\Domain\Exception\MaintenanceCostException;
use Shared\Domain\Exception\InvalidValueException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Throwable;

use function is_string;
use function str_starts_with;

/** Class MaintenanceCostFailureSubscriber. Module-owned financial and revision refusal mapping. @category EventSubscriber */
final readonly class MaintenanceCostFailureSubscriber implements EventSubscriberInterface
{
  /**
   * @return array<string,array{string,int}>
   */
  public static function getSubscribedEvents(): array
  {
    return [KernelEvents::EXCEPTION => ['onException', 11]];
  }

  public function onException(ExceptionEvent $event): void
  {
    $operation = $event->getRequest()->attributes->get('_api_operation_name', '');
    $costOperation = is_string($operation) && (str_starts_with($operation, 'maintenance_cost_') || str_starts_with($operation, 'maintenance_economic_'));
    $error = $event->getThrowable();
    do {
      $reason = $this->reason($error, $costOperation);
      if (null !== $reason) {
        $status = match ($reason) {
          'maintenance_cost_not_found' => 404,
          'maintenance_cost_access_denied' => 403,
          'maintenance_cost_conflict' => 409,
          'maintenance_cost_revision_stale' => 412,
          'maintenance_cost_precondition_required' => 428,
          default => 422,
        };
        $event->setResponse(new JsonResponse(['type' => '/errors/' . $reason, 'title' => 'Maintenance cost operation refused', 'status' => $status, 'code' => $reason, 'detail' => $error->getMessage()], $status, ['Content-Type' => 'application/problem+json']));

        return;
      }
      $error = $error->getPrevious();
    } while (null !== $error);
  }

  /**
   * Method reason
   *
   * Limits generic validation errors to this module's operations.
   *
   * @access private
   *
   * @param Throwable $error failure at the current exception-chain position
   * @param bool $costOperation whether this request belongs to the financial surface
   *
   * @return ?string module error code when owned by this subscriber
   */
  private function reason(Throwable $error, bool $costOperation): ?string
  {
    if ($error instanceof MaintenanceCostException) {
      return $error->reason;
    }

    return $costOperation && ($error instanceof InvalidValueException || $error instanceof NotNormalizableValueException) ? 'maintenance_cost_invalid' : null;
  }
}
