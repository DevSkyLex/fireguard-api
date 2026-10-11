<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Inspection\CreateInspection;

use DateTimeImmutable;
use Exception;
use Inspection\Application\Port\Outbound\{ChecklistValidationPort, EquipmentValidationPort, FacilityValidationPort};
use Inspection\Application\Port\Outbound\Internal\InspectionRepositoryPort;
use Inspection\Domain\Exception\{InspectionAccessDeniedException, InspectionNotFoundException};
use Inspection\Domain\Model\Inspection\{Inspection, InspectionCreationOptions};
use Inspection\Domain\ValueObject\{
  InspectionChecklistId,
  InspectionEquipmentId,
  InspectionFacilityId,
  InspectionId,
  InspectionOrganizationId,
  InspectionResult,
  Inspector,
  InspectorType
};
use Intervention\Application\Contract\Resource\InterventionResourceType;
use Intervention\Application\Port\Inbound\InterventionResourcesPort;
use InvalidArgumentException;
use Organization\Application\Contract\Quota\OrganizationQuotaResource;
use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, OrganizationQuotaPort};
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{TransactionManagerPort, UuidGeneratorPort};
use Shared\Domain\Exception\InvalidValueException;
use Throwable;
use User\Application\Port\Inbound\UserDirectoryPort;
use ValueError;

/**
 * UseCase CreateInspectionHandler.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateInspectionHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the CreateInspectionHandler class.
   *
   * @since 1.0.0
   *
   * @param InspectionRepositoryPort $inspectionRepository the inspection repository value
   * @param EquipmentValidationPort $equipmentValidation the equipment validation value
   * @param FacilityValidationPort $facilityValidation the facility validation value
   * @param ChecklistValidationPort $checklistValidation the checklist validation value
   * @param UuidGeneratorPort $uuidFactory the uuid factory value
   * @param OrganizationQuotaPort $quota the organization quota enforcement port
   * @param TransactionManagerPort $transactionManager the transaction manager
   */
  public function __construct(
    private \Inspection\Application\Port\Outbound\ChecklistLockPort $locks,
    private InspectionRepositoryPort $inspectionRepository,
    private EquipmentValidationPort $equipmentValidation,
    private FacilityValidationPort $facilityValidation,
    private ChecklistValidationPort $checklistValidation,
    private UuidGeneratorPort $uuidFactory,
    private OrganizationQuotaPort $quota,
    private TransactionManagerPort $transactionManager,
    private InterventionResourcesPort $interventionResources,
    private OrganizationAuthorizationPort $authorization,
    private ?UserDirectoryPort $users = null,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Handles the corresponding use case execution.
   *
   * @since 1.0.0
   *
   * @param CreateInspectionCommand $command the command payload
   *
   * @return CreateInspectionResult the use case result
   */
  public function __invoke(CreateInspectionCommand $command): CreateInspectionResult
  {
    return $this->transactionManager->transactional(function () use ($command): CreateInspectionResult {
      if (null !== $command->actorUserId) {
        $permission = null === $command->interventionId ? 'organization.inspection.write'
          : $this->interventionResources->mutationPermission($command->interventionId, $command->actorUserId, $command->organizationId);
        $decision = $this->authorization->resolveAccess($command->actorUserId, $command->organizationId, $permission);
        if ($decision->isOutsideScope()) {
          throw new InspectionNotFoundException('Organization not found.');
        }
        if (!$decision->isGranted()) {
          throw new InspectionAccessDeniedException('Missing ' . $permission . ' permission.');
        }
      }
      $this->interventionResources->assertOfflineCreate(InterventionResourceType::INSPECTION, $command->clientId);

      try {
        $this->equipmentValidation->assertEquipmentIsInspectable($command->equipmentId, $command->organizationId, $command->facilityId);

        $organizationId = InspectionOrganizationId::fromString($command->organizationId);
        $equipmentId = InspectionEquipmentId::fromString($command->equipmentId);
        $facilityId = null !== $command->facilityId ? InspectionFacilityId::fromString($command->facilityId) : null;
        $checklistId = null !== $command->checklistId ? InspectionChecklistId::fromString($command->checklistId) : null;

        $result = InspectionResult::from($command->result);
        $inspectorType = InspectorType::from($command->inspectorType);

        $inspector = match ($inspectorType) {
          InspectorType::USER => Inspector::forUser(
            userId: ('' === $command->inspectorUserId ? null : $command->inspectorUserId) ?? $command->actorUserId ?? throw InvalidValueException::because('User ID is required for USER inspector type.'),
            name: $command->inspectorName,
          ),
          InspectorType::EXTERNAL => Inspector::forExternal(
            name: $command->inspectorName,
            organizationName: $command->inspectorOrganizationName,
          ),
        };

        $performedAt = new DateTimeImmutable($command->performedAt);

        /** @var InspectionId $inspectionId */
        $inspectionId = null === $command->resourceId
          ? new InspectionId($this->uuidFactory->generate())
          : InspectionId::fromString($command->resourceId);

        $inspection = Inspection::create(
          id: $inspectionId,
          organizationId: $organizationId,
          equipmentId: $equipmentId,
          inspector: $inspector,
          result: $result,
          performedAt: $performedAt,
          options: new InspectionCreationOptions(
            facilityId: $facilityId,
            checklistId: $checklistId,
            notes: $command->notes,
            signature: $command->signature,
          ),
        );
      } catch (InvalidValueException|ValueError $exception) {
        throw InvalidValueException::because($exception->getMessage(), $exception);
      } catch (Exception $exception) {
        if (!$exception instanceof InvalidArgumentException) {
          throw InvalidValueException::because($exception->getMessage(), $exception);
        }

        throw $exception;
      }

      // Enforce the plan quota and persist atomically: assertCanAdd takes a
      // transaction-scoped advisory lock so concurrent creates at the cap cannot
      // both slip through the count (see OrganizationQuotaPort::assertCanAdd).
      $this->transactionManager->transactional(function () use ($command, $inspection): void {
        if (null !== $command->facilityId) {
          $this->facilityValidation->assertFacilityIsUsable($command->facilityId, $command->organizationId, $command->interventionId);
        }
        $this->quota->assertCanAdd($command->organizationId, OrganizationQuotaResource::INSPECTIONS);
        $this->locks->withLock($command->organizationId, $command->checklistId, function () use ($command, $inspection): void {
          if (null !== $command->checklistId) {
            $this->checklistValidation->assertChecklistIsUsable($command->checklistId, $command->organizationId);
          }
          $this->inspectionRepository->save($inspection);
        });
      });

      $assignment = $this->interventionResources->attach(InterventionResourceType::INSPECTION, (string) $inspection->id(), $command->organizationId, $command->interventionId, $command->clientId);

      return new CreateInspectionResult(
        inspectionId: (string) $inspection->id(),
        organizationId: (string) $inspection->organizationId(),
        equipmentId: (string) $inspection->equipmentId(),
        facilityId: $inspection->facilityId()?->__toString(),
        result: $inspection->result()->value,
        status: $inspection->status()->value,
        performedAt: $inspection->performedAt()->format('c'),
        inspectorType: $inspection->inspector()->type->value,
        inspectorName: $inspection->inspector()->name,
        inspectorUserId: $inspection->inspector()->userId,
        inspectorOrganizationName: $inspection->inspector()->organizationName,
        checklistId: $inspection->checklistId()?->__toString(),
        notes: $inspection->notes(),
        signature: $inspection->signature(),
        createdAt: $inspection->createdAt(),
        updatedAt: $inspection->updatedAt(),
        interventionId: $assignment->interventionId,
        recordStatus: $assignment->recordStatus,
        revision: $assignment->revision,
        inspectorProfile: $this->profile($inspection->inspector()->userId),
      );
    });
  }

  private function profile(?string $userId): ?\User\Application\Contract\User\UserView
  {
    try {
      return null === $userId ? null : $this->users?->findById($userId);
    } catch (Throwable) {
      return null;
    }
  }
  // #endregion
}
