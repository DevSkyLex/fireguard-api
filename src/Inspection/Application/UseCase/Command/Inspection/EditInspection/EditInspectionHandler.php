<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Inspection\EditInspection;

use DateTimeImmutable;
use Exception;
use Inspection\Application\Port\Outbound\{ChecklistValidationPort, EquipmentValidationPort, FacilityValidationPort, InspectionRepositoryPort};
use Inspection\Domain\Exception\InspectionNotFoundException;
use Inspection\Domain\ValueObject\{InspectionChecklistId, InspectionEquipmentId, InspectionFacilityId, InspectionId, InspectionOrganizationId, InspectionResult};
use InvalidArgumentException;
use Shared\Application\Message\CommandHandler;
use Shared\Domain\Exception\InvalidValueException;
use ValueError;

final readonly class EditInspectionHandler implements CommandHandler
{
  public function __construct(
    private InspectionRepositoryPort $inspectionRepository,
    private EquipmentValidationPort $equipmentValidation,
    private FacilityValidationPort $facilityValidation,
    private ChecklistValidationPort $checklistValidation,
  ) {
  }

  public function __invoke(EditInspectionCommand $command): EditInspectionResult
  {
    try {
      $inspectionId = InspectionId::fromString($command->inspectionId);
      $organizationId = InspectionOrganizationId::fromString($command->organizationId);

      if ($command->hasEquipmentId) {
        if (null === $command->equipmentId) {
          throw new InvalidArgumentException('Field "equipmentId" cannot be null when provided.');
        }

        $this->equipmentValidation->assertEquipmentExists($command->equipmentId, $command->organizationId);
      }

      if ($command->hasFacilityId && null !== $command->facilityId) {
        $this->facilityValidation->assertFacilityIsUsable($command->facilityId, $command->organizationId);
      }

      if ($command->hasChecklistId && null !== $command->checklistId) {
        $this->checklistValidation->assertChecklistIsUsable($command->checklistId, $command->organizationId);
      }

      $equipmentId = $command->hasEquipmentId ? InspectionEquipmentId::fromString($command->equipmentId) : null;
      $facilityId = $command->hasFacilityId && null !== $command->facilityId
        ? InspectionFacilityId::fromString($command->facilityId)
        : null;
      $checklistId = $command->hasChecklistId && null !== $command->checklistId
        ? InspectionChecklistId::fromString($command->checklistId)
        : null;
      $result = $command->hasResult && null !== $command->result ? InspectionResult::from($command->result) : null;
      $performedAt = $command->hasPerformedAt && null !== $command->performedAt
        ? new DateTimeImmutable($command->performedAt)
        : null;
    } catch (InvalidValueException|ValueError $exception) {
      throw new InvalidArgumentException($exception->getMessage(), 0, $exception);
    } catch (Exception $exception) {
      if (!$exception instanceof InvalidArgumentException) {
        throw new InvalidArgumentException($exception->getMessage(), 0, $exception);
      }

      throw $exception;
    }

    $inspection = $this->inspectionRepository->findPublishedById($inspectionId);

    if (null === $inspection || (string) $inspection->organizationId() !== (string) $organizationId) {
      throw InspectionNotFoundException::withId($command->inspectionId);
    }

    $inspection->edit(
      equipmentId: $equipmentId,
      facilityId: $facilityId,
      checklistId: $checklistId,
      result: $result,
      performedAt: $performedAt,
      notes: $command->notes,
      signature: $command->signature,
      hasEquipmentId: $command->hasEquipmentId,
      hasFacilityId: $command->hasFacilityId,
      hasChecklistId: $command->hasChecklistId,
      hasResult: $command->hasResult,
      hasPerformedAt: $command->hasPerformedAt,
      hasNotes: $command->hasNotes,
      hasSignature: $command->hasSignature,
    );

    $this->inspectionRepository->save($inspection);

    return new EditInspectionResult(
      inspectionId: (string) $inspection->id(),
      organizationId: (string) $inspection->organizationId(),
      updatedAt: $inspection->updatedAt(),
    );
  }
}
