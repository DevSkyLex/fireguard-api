<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\EventSubscriber;

use Equipment\Domain\Exception\{EquipmentNotFoundException, EquipmentReplacementConflictException, EquipmentSerialNumberAlreadyExistsException};
use Organization\Application\Contract\Quota\OrganizationQuotaExceededException;
use Shared\Domain\Exception\InvalidValueException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Class EquipmentReplacementFailureSubscriber
 *
 * Maps replacement failures centrally, including command-bus exception wrappers.
 *
 * @category EventSubscriber
 */
final readonly class EquipmentReplacementFailureSubscriber implements EventSubscriberInterface
{
  // #region Methods
  /**
   * Method getSubscribedEvents
   *
   * @access public
   *
   * @return array<string, array{string, int}> the exception listener
   */
  public static function getSubscribedEvents(): array
  {
    return [KernelEvents::EXCEPTION => ['onException', 12]];
  }

  /**
   * Method onException
   *
   * @access public
   *
   * @param ExceptionEvent $event the current kernel exception
   *
   * @return void
   */
  public function onException(ExceptionEvent $event): void
  {
    $request = $event->getRequest();
    if ('equipment_replace' !== $request->attributes->get('_api_operation_name')) {
      return;
    }
    $error = $event->getThrowable();
    do {
      $status = match (true) {
        $error instanceof EquipmentReplacementConflictException => 409,
        $error instanceof EquipmentSerialNumberAlreadyExistsException => 409,
        $error instanceof OrganizationQuotaExceededException => 409,
        $error instanceof EquipmentNotFoundException => 404,
        $error instanceof InvalidValueException => 422,
        default => null,
      };
      if (null !== $status) {
        $code = match (true) {
          $error instanceof EquipmentNotFoundException => 'equipment_not_found',
          $error instanceof EquipmentSerialNumberAlreadyExistsException => 'equipment_serial_number_conflict',
          $error instanceof OrganizationQuotaExceededException => 'organization_quota_exceeded',
          $error instanceof InvalidValueException => 'equipment_replacement_invalid',
          default => 'equipment_replacement_conflict',
        };
        $event->setResponse(new JsonResponse([
          'type' => '/errors/' . $code,
          'title' => 404 === $status ? 'Equipment not found' : 'Replacement refused',
          'status' => $status, 'code' => $code, 'detail' => $error->getMessage(),
        ], $status, ['Content-Type' => 'application/problem+json']));

        return;
      }
      $error = $error->getPrevious();
    } while (null !== $error);
  }
  // #endregion
}
