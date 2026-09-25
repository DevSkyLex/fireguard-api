<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Processor\Equipment;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Equipment\Application\Contract\FloorPlan\{
  FloorPlanAttachmentNotAncestorException,
  FloorPlanAttachmentNotFloorPlanException,
  FloorPlanAttachmentNotFoundException
};
use Equipment\Application\UseCase\Command\Equipment\SetEquipmentPlanPosition\{SetEquipmentPlanPositionCommand, SetEquipmentPlanPositionResult};
use Equipment\Domain\Exception\{
  EquipmentAlreadyDecommissionedException,
  EquipmentNotAssignedToFacilityException,
  EquipmentNotFoundException
};
use Equipment\Presentation\Api\Dto\Input\Equipment\SetEquipmentPlanPositionInput;
use Equipment\Presentation\Api\Dto\Output\Equipment\EquipmentOutput;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Exception\{MessengerExceptionUnwrapperTrait, MessengerRuntimeException};
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException, NotFoundHttpException};
use Throwable;

use function is_string;

/**
 * Processor SetEquipmentPlanPositionProcessor.
 *
 * Handles `PUT /organizations/{organizationId}/equipment/{equipmentId}/plan-position`.
 * PUT rather than assign/unassign's POST: this is an idempotent full-replace
 * (or clear-with-null) of one field, mirroring Facility's own
 * `PUT .../plan-geometry` sibling endpoint — not a stateful transition with
 * side effects (maintenance-log closing, status reset) the way assign/unassign
 * are.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<SetEquipmentPlanPositionInput, EquipmentOutput>
 */
final readonly class SetEquipmentPlanPositionProcessor implements ProcessorInterface
{
  use MessengerExceptionUnwrapperTrait;

  // #region Constructor
  public function __construct(
    private CommandBusPort $commandBus,
    private OrganizationAuthorizationPort $authorization,
    private Security $security,
    private \Equipment\Presentation\Api\Factory\EquipmentDetailOutputFactory $detail,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method process.
   *
   * @since 1.0.0
   *
   * @param mixed $data the input data
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): EquipmentOutput
  {
    /** @var SetEquipmentPlanPositionInput $data */
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    $equipmentId = $uriVariables['equipmentId'] ?? null;

    if (!is_string($organizationId) || '' === $organizationId || !is_string($equipmentId) || '' === $equipmentId) {
      throw new BadRequestHttpException('OrganizationId and equipmentId URI parameters are required.');
    }

    $decision = $this->authorization->resolveAccess($user->getId(), $organizationId, 'organization.equipment.write');
    if ($decision->isOutsideScope()) {
      throw new NotFoundHttpException('Organization not found.');
    }
    if (!$decision->isGranted()) {
      throw new AccessDeniedHttpException('Missing organization.equipment.write permission.');
    }

    try {
      /** @var SetEquipmentPlanPositionResult $result */
      $result = $this->commandBus->dispatch(new SetEquipmentPlanPositionCommand(
        organizationId: $organizationId,
        equipmentId: $equipmentId,
        attachmentId: $data->attachmentId,
        x: $data->x,
        y: $data->y,
      ));
    } catch (EquipmentNotFoundException|FloorPlanAttachmentNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (
      EquipmentAlreadyDecommissionedException|
      EquipmentNotAssignedToFacilityException|
      FloorPlanAttachmentNotFloorPlanException|
      FloorPlanAttachmentNotAncestorException $exception
    ) {
      throw new ConflictHttpException($exception->getMessage(), $exception);
    } catch (InvalidArgumentException $exception) {
      throw new BadRequestHttpException($exception->getMessage(), $exception);
    } catch (MessengerRuntimeException $exception) {
      throw $this->mapMessengerFailure($exception);
    }

    return $this->detail->read($organizationId, $equipmentId);
  }

  /**
   * Preserve the HTTP status of domain failures wrapped by the command bus.
   */
  private function mapMessengerFailure(MessengerRuntimeException $exception): Throwable
  {
    $notFound = $this->findException($exception, EquipmentNotFoundException::class);
    if ($notFound instanceof EquipmentNotFoundException) {
      return new NotFoundHttpException($notFound->getMessage(), $exception);
    }

    $attachmentNotFound = $this->findException($exception, FloorPlanAttachmentNotFoundException::class);
    if ($attachmentNotFound instanceof FloorPlanAttachmentNotFoundException) {
      return new NotFoundHttpException($attachmentNotFound->getMessage(), $exception);
    }

    $decommissioned = $this->findException($exception, EquipmentAlreadyDecommissionedException::class);
    if ($decommissioned instanceof EquipmentAlreadyDecommissionedException) {
      return new ConflictHttpException($decommissioned->getMessage(), $exception);
    }

    $notAssigned = $this->findException($exception, EquipmentNotAssignedToFacilityException::class);
    if ($notAssigned instanceof EquipmentNotAssignedToFacilityException) {
      return new ConflictHttpException($notAssigned->getMessage(), $exception);
    }

    $notFloorPlan = $this->findException($exception, FloorPlanAttachmentNotFloorPlanException::class);
    if ($notFloorPlan instanceof FloorPlanAttachmentNotFloorPlanException) {
      return new ConflictHttpException($notFloorPlan->getMessage(), $exception);
    }

    $notAncestor = $this->findException($exception, FloorPlanAttachmentNotAncestorException::class);
    if ($notAncestor instanceof FloorPlanAttachmentNotAncestorException) {
      return new ConflictHttpException($notAncestor->getMessage(), $exception);
    }

    $invalidArgument = $this->findException($exception, InvalidArgumentException::class);
    if ($invalidArgument instanceof InvalidArgumentException) {
      return new BadRequestHttpException($invalidArgument->getMessage(), $exception);
    }

    return $exception;
  }
  // #endregion
}
