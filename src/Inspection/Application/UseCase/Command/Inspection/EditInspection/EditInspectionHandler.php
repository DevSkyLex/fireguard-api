<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Inspection\EditInspection;

use DateTimeImmutable;
use Exception;
use Inspection\Application\Port\Outbound\ChecklistLockPort;
use Inspection\Application\Port\Outbound\{ChecklistValidationPort, EquipmentValidationPort, FacilityValidationPort, InspectionRepositoryPort};
use Inspection\Domain\Exception\InspectionNotFoundException;
use Inspection\Domain\ValueObject\{InspectionChecklistId, InspectionEquipmentId, InspectionFacilityId, InspectionFindingPatch, InspectionId, InspectionOrganizationId, InspectionReferencePatch, InspectionResult, InspectionTextPatch};
use InvalidArgumentException;
use Shared\Application\Message\CommandHandler;
use Shared\Domain\Exception\InvalidValueException;
use ValueError;

/**
 * Class EditInspectionHandler
 *
 * Applies an inspection patch after validating referenced equipment, facility and checklist records.
 *
 * @category Handler
 */
final readonly class EditInspectionHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Initializes the checklist lock and reference validation dependencies.
   *
   * @access public
   *
   * @param ChecklistLockPort $locks checklist mutation lock
   * @param InspectionRepositoryPort $inspectionRepository inspection persistence
   * @param EquipmentValidationPort $equipmentValidation equipment reference validation
   * @param FacilityValidationPort $facilityValidation facility reference validation
   * @param ChecklistValidationPort $checklistValidation checklist reference validation
   *
   * @return void
   */
  public function __construct(
    private ChecklistLockPort $locks,
    private InspectionRepositoryPort $inspectionRepository,
    private EquipmentValidationPort $equipmentValidation,
    private FacilityValidationPort $facilityValidation,
    private ChecklistValidationPort $checklistValidation,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke
   *
   * Runs the inspection edit while holding the relevant checklist lock.
   *
   * @access public
   *
   * @param EditInspectionCommand $command requested inspection changes
   *
   * @return EditInspectionResult updated inspection identifiers and timestamp
   */
  public function __invoke(EditInspectionCommand $command): EditInspectionResult
  {
    return $this->locks->withLock($command->organizationId, $command->hasChecklistId ? $command->checklistId : null, fn (): EditInspectionResult => $this->execute($command));
  }

  /**
   * Method execute
   *
   * Loads the inspection, applies the requested patch and persists the result.
   *
   * @access private
   *
   * @param EditInspectionCommand $command requested inspection changes
   *
   * @return EditInspectionResult updated inspection identifiers and timestamp
   *
   * @throws InspectionNotFoundException when the inspection is missing from the organization
   */
  private function execute(EditInspectionCommand $command): EditInspectionResult
  {
    try {
      $inspectionId = InspectionId::fromString($command->inspectionId);
      $organizationId = InspectionOrganizationId::fromString($command->organizationId);

      $this->assertReferences($command);

      /** @var string $validatedEquipmentId assertReferences() rejects null when equipmentId is provided. */
      $validatedEquipmentId = $command->equipmentId;
      $equipmentId = $command->hasEquipmentId ? InspectionEquipmentId::fromString($validatedEquipmentId) : null;
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
      throw InvalidValueException::because($exception->getMessage(), $exception);
    } catch (Exception $exception) {
      if (!$exception instanceof InvalidArgumentException) {
        throw InvalidValueException::because($exception->getMessage(), $exception);
      }

      throw $exception;
    }

    $inspection = $this->inspectionRepository->findPublishedById($inspectionId);

    if (null === $inspection || (string) $inspection->organizationId() !== (string) $organizationId) {
      throw InspectionNotFoundException::withId($command->inspectionId);
    }

    $inspection->edit(
      references: new InspectionReferencePatch(
        equipmentId: $equipmentId,
        hasEquipmentId: $command->hasEquipmentId,
        facilityId: $facilityId,
        hasFacilityId: $command->hasFacilityId,
        checklistId: $checklistId,
        hasChecklistId: $command->hasChecklistId,
      ),
      finding: new InspectionFindingPatch(
        result: $result,
        hasResult: $command->hasResult,
        performedAt: $performedAt,
        hasPerformedAt: $command->hasPerformedAt,
        text: new InspectionTextPatch(
          notes: $command->notes,
          hasNotes: $command->hasNotes,
          signature: $command->signature,
          hasSignature: $command->hasSignature,
        ),
      ),
    );

    $this->inspectionRepository->save($inspection);

    return new EditInspectionResult(
      inspectionId: (string) $inspection->id(),
      organizationId: (string) $inspection->organizationId(),
      updatedAt: $inspection->updatedAt(),
    );
  }

  /**
   * Method assertReferences
   *
   * Validates every supplied equipment, facility and checklist reference.
   *
   * @access private
   *
   * @param EditInspectionCommand $command requested inspection changes
   *
   * @return void
   *
   * @throws InvalidValueException when a supplied reference cannot be used
   */
  private function assertReferences(EditInspectionCommand $command): void
  {
    if ($command->hasEquipmentId) {
      if (null === $command->equipmentId) {
        throw InvalidValueException::because('Field "equipmentId" cannot be null when provided.');
      }

      $this->equipmentValidation->assertEquipmentExists($command->equipmentId, $command->organizationId);
    }
    if ($command->hasFacilityId && null !== $command->facilityId) {
      $this->facilityValidation->assertFacilityIsUsable($command->facilityId, $command->organizationId);
    }
    if ($command->hasChecklistId && null !== $command->checklistId) {
      $this->checklistValidation->assertChecklistIsUsable($command->checklistId, $command->organizationId);
    }
  }
  // #endregion
}
