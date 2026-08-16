<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Processor\Equipment;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Equipment\Application\UseCase\Command\Equipment\SetEquipmentPlanPosition\{SetEquipmentPlanPositionCommand, SetEquipmentPlanPositionResult};
use Equipment\Domain\Exception\{
  EquipmentAlreadyDecommissionedException,
  EquipmentNotAssignedToFacilityException,
  EquipmentNotFoundException,
  FloorPlanAttachmentNotAncestorException,
  FloorPlanAttachmentNotFloorPlanException,
  FloorPlanAttachmentNotFoundException
};
use Equipment\Presentation\Api\Dto\Input\Equipment\SetEquipmentPlanPositionInput;
use Equipment\Presentation\Api\Dto\Output\Equipment\{EquipmentOutput, TagOutput};
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Exception\{MessengerExceptionUnwrapperTrait, MessengerRuntimeException};
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException, NotFoundHttpException};

use function array_map;
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
      $notFound = $this->findException($exception, EquipmentNotFoundException::class);
      if ($notFound instanceof EquipmentNotFoundException) {
        throw new NotFoundHttpException($notFound->getMessage(), $exception);
      }

      $attachmentNotFound = $this->findException($exception, FloorPlanAttachmentNotFoundException::class);
      if ($attachmentNotFound instanceof FloorPlanAttachmentNotFoundException) {
        throw new NotFoundHttpException($attachmentNotFound->getMessage(), $exception);
      }

      $decommissioned = $this->findException($exception, EquipmentAlreadyDecommissionedException::class);
      if ($decommissioned instanceof EquipmentAlreadyDecommissionedException) {
        throw new ConflictHttpException($decommissioned->getMessage(), $exception);
      }

      $notAssigned = $this->findException($exception, EquipmentNotAssignedToFacilityException::class);
      if ($notAssigned instanceof EquipmentNotAssignedToFacilityException) {
        throw new ConflictHttpException($notAssigned->getMessage(), $exception);
      }

      $notFloorPlan = $this->findException($exception, FloorPlanAttachmentNotFloorPlanException::class);
      if ($notFloorPlan instanceof FloorPlanAttachmentNotFloorPlanException) {
        throw new ConflictHttpException($notFloorPlan->getMessage(), $exception);
      }

      $notAncestor = $this->findException($exception, FloorPlanAttachmentNotAncestorException::class);
      if ($notAncestor instanceof FloorPlanAttachmentNotAncestorException) {
        throw new ConflictHttpException($notAncestor->getMessage(), $exception);
      }

      $invalidArgument = $this->findException($exception, InvalidArgumentException::class);
      if ($invalidArgument instanceof InvalidArgumentException) {
        throw new BadRequestHttpException($invalidArgument->getMessage(), $exception);
      }

      throw $exception;
    }

    $output = new EquipmentOutput();
    $output->id = $result->equipmentId;
    $output->organizationId = $result->organizationId;
    $output->facilityId = $result->facilityId;
    $output->type = $result->type;
    $output->subType = $result->subType;
    $output->brand = $result->brand;
    $output->model = $result->model;
    $output->serialNumber = $result->serialNumber;
    $output->locationLabel = $result->locationLabel;
    $output->status = $result->status;
    $output->installedAt = $result->installedAt;
    $output->commissionedAt = $result->commissionedAt;
    $output->tags = array_map(TagOutput::fromArray(...), $result->tags);
    $output->createdAt = $result->createdAt->format('c');
    $output->updatedAt = $result->updatedAt->format('c');
    $output->planPosition = $result->planPosition;

    return $output;
  }
  // #endregion
}
