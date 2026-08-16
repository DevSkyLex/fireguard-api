<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Command\Equipment\SetEquipmentPlanPosition;

use Equipment\Application\Port\Outbound\{EquipmentFloorPlanValidationPort, EquipmentRepositoryPort, TagRepositoryPort};
use Equipment\Domain\Exception\{EquipmentNotAssignedToFacilityException, EquipmentNotFoundException};
use Equipment\Domain\Model\Equipment\Equipment;
use Equipment\Domain\ValueObject\{EquipmentId, EquipmentOrganizationId, PlanPosition};
use InvalidArgumentException;
use Shared\Application\Message\CommandHandler;
use Shared\Domain\Exception\InvalidValueException;

use function array_map;

/**
 * UseCase SetEquipmentPlanPositionHandler.
 *
 * Validates, through `EquipmentFloorPlanValidationPort` and before any
 * durable save, that the target attachment exists, is a floor plan, and
 * belongs to the equipment's own facility or one of its ancestors — the
 * business rule the Presentation layer must never re-implement. Equipment
 * with no facility assignment cannot be placed
 * ({@see EquipmentNotAssignedToFacilityException}). `null` `attachmentId`
 * (with `null` `x`/`y`) clears the position.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SetEquipmentPlanPositionHandler implements CommandHandler
{
  // #region Constructor
  public function __construct(
    private EquipmentRepositoryPort $equipmentRepository,
    private EquipmentFloorPlanValidationPort $floorPlanValidation,
    private TagRepositoryPort $tagRepository,
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
   * @param SetEquipmentPlanPositionCommand $command the command payload
   *
   * @return SetEquipmentPlanPositionResult the use case result
   */
  public function __invoke(SetEquipmentPlanPositionCommand $command): SetEquipmentPlanPositionResult
  {
    try {
      $equipmentId = EquipmentId::fromString($command->equipmentId);
      $organizationId = EquipmentOrganizationId::fromString($command->organizationId);
    } catch (InvalidValueException $exception) {
      throw new InvalidArgumentException($exception->getMessage(), 0, $exception);
    }

    $equipment = $this->equipmentRepository->findById($equipmentId);

    if (null === $equipment || (string) $equipment->organizationId() !== (string) $organizationId) {
      throw EquipmentNotFoundException::withId($command->equipmentId);
    }

    if (null === $command->attachmentId && null === $command->x && null === $command->y) {
      $equipment->removeFromPlan();
      $this->equipmentRepository->save($equipment);

      return $this->result($equipment);
    }

    if (null === $command->attachmentId || null === $command->x || null === $command->y) {
      throw new InvalidArgumentException('Fields "attachmentId", "x" and "y" must be provided together, or all omitted to clear the plan position.');
    }

    $facilityId = $equipment->facilityId();
    if (null === $facilityId) {
      throw EquipmentNotAssignedToFacilityException::withId($command->equipmentId);
    }

    $this->floorPlanValidation->assertAttachmentUsableForFacility($command->attachmentId, (string) $facilityId);

    try {
      $planPosition = new PlanPosition($command->attachmentId, $command->x, $command->y);
    } catch (InvalidValueException $exception) {
      throw new InvalidArgumentException($exception->getMessage(), 0, $exception);
    }

    $equipment->placeOnPlan($planPosition);
    $this->equipmentRepository->save($equipment);

    return $this->result($equipment);
  }

  /**
   * Method result.
   *
   * @since 1.0.0
   *
   * @param Equipment $equipment the mutated equipment aggregate
   *
   * @return SetEquipmentPlanPositionResult the mapped result
   */
  private function result(Equipment $equipment): SetEquipmentPlanPositionResult
  {
    $tags = $this->tagRepository->findByEquipmentId($equipment->id());

    return new SetEquipmentPlanPositionResult(
      equipmentId: (string) $equipment->id(),
      organizationId: (string) $equipment->organizationId(),
      facilityId: $equipment->facilityId()?->__toString(),
      type: $equipment->type()->value,
      subType: $equipment->subType(),
      brand: $equipment->brand(),
      model: $equipment->model(),
      serialNumber: $equipment->serialNumber(),
      locationLabel: $equipment->locationLabel(),
      status: $equipment->status()->value,
      installedAt: $equipment->installedAt()?->format('c'),
      commissionedAt: $equipment->commissionedAt()?->format('c'),
      tags: array_map(
        static fn ($tag): array => [
          'id' => (string) $tag->id(),
          'name' => $tag->name(),
          'organizationId' => (string) $tag->organizationId(),
        ],
        $tags,
      ),
      createdAt: $equipment->createdAt(),
      updatedAt: $equipment->updatedAt(),
      planPosition: $equipment->planPosition()?->toArray(),
    );
  }
  // #endregion
}
