<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Inspection\CreateInspection;

use DateTimeImmutable;
use Exception;
use Inspection\Application\Port\Outbound\{ChecklistValidationPort, EquipmentValidationPort, FacilityValidationPort, InspectionRepositoryPort};
use Inspection\Domain\Model\Inspection\Inspection;
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
use InvalidArgumentException;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Message\CommandHandler;
use Shared\Domain\Exception\InvalidValueException;
use ValueError;

/**
 * UseCase CreateInspectionHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateInspectionHandler implements CommandHandler
{
  // #region Constructor
  public function __construct(
    private InspectionRepositoryPort $inspectionRepository,
    private EquipmentValidationPort $equipmentValidation,
    private FacilityValidationPort $facilityValidation,
    private ChecklistValidationPort $checklistValidation,
    private UuidFactory $uuidFactory,
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
    $this->equipmentValidation->assertEquipmentExists($command->equipmentId, $command->organizationId);

    if (null !== $command->facilityId) {
      $this->facilityValidation->assertFacilityIsUsable($command->facilityId, $command->organizationId);
    }

    if (null !== $command->checklistId) {
      $this->checklistValidation->assertChecklistIsUsable($command->checklistId, $command->organizationId);
    }

    try {
      $organizationId = InspectionOrganizationId::fromString($command->organizationId);
      $equipmentId = InspectionEquipmentId::fromString($command->equipmentId);
      $facilityId = null !== $command->facilityId ? InspectionFacilityId::fromString($command->facilityId) : null;
      $checklistId = null !== $command->checklistId ? InspectionChecklistId::fromString($command->checklistId) : null;

      $result = InspectionResult::from($command->result);
      $inspectorType = InspectorType::from($command->inspectorType);

      $inspector = match ($inspectorType) {
        InspectorType::USER => Inspector::forUser(
          userId: $command->inspectorUserId ?? throw new InvalidArgumentException('User ID is required for USER inspector type.'),
          name: $command->inspectorName,
        ),
        InspectorType::EXTERNAL => Inspector::forExternal(
          name: $command->inspectorName,
          organizationName: $command->inspectorOrganizationName,
        ),
      };

      $performedAt = new DateTimeImmutable($command->performedAt);

      /** @var InspectionId $inspectionId */
      $inspectionId = $this->uuidFactory->create(InspectionId::class);

      $inspection = Inspection::create(
        id: $inspectionId,
        organizationId: $organizationId,
        equipmentId: $equipmentId,
        inspector: $inspector,
        result: $result,
        performedAt: $performedAt,
        facilityId: $facilityId,
        checklistId: $checklistId,
        notes: $command->notes,
        signature: $command->signature,
      );
    } catch (InvalidValueException|ValueError $exception) {
      throw new InvalidArgumentException($exception->getMessage(), 0, $exception);
    } catch (Exception $exception) {
      if (!$exception instanceof InvalidArgumentException) {
        throw new InvalidArgumentException($exception->getMessage(), 0, $exception);
      }

      throw $exception;
    }

    $this->inspectionRepository->save($inspection);

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
    );
  }
  // #endregion
}
