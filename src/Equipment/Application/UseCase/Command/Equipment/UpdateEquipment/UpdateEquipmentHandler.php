<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Command\Equipment\UpdateEquipment;

use Equipment\Application\Port\Outbound\{EquipmentRepositoryPort, FacilityNamingPort, TagRepositoryPort};
use Equipment\Domain\Exception\EquipmentNotFoundException;
use Equipment\Domain\Model\Equipment\Equipment;
use Equipment\Domain\ValueObject\{EquipmentId, EquipmentOrganizationId, EquipmentType};
use Shared\Application\Message\CommandHandler;
use Shared\Domain\Exception\InvalidValueException;
use ValueError;

use function array_map;

/**
 * UseCase UpdateEquipmentHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateEquipmentHandler implements CommandHandler
{
  // #region Constructor
  public function __construct(
    private EquipmentRepositoryPort $equipmentRepository,
    private TagRepositoryPort $tagRepository,
    private FacilityNamingPort $facilityNaming,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   */
  public function __invoke(UpdateEquipmentCommand $command): UpdateEquipmentResult
  {
    $equipmentId = EquipmentId::fromString($command->equipmentId);
    $organizationId = EquipmentOrganizationId::fromString($command->organizationId);

    $equipment = $this->equipmentRepository->findById($equipmentId);

    if (null === $equipment || (string) $equipment->organizationId() !== (string) $organizationId) {
      throw EquipmentNotFoundException::withId($command->equipmentId);
    }

    try {
      $equipment->update(
        type: EquipmentType::from($command->type),
        subType: $command->subType,
        brand: $command->brand,
        model: $command->model,
        serialNumber: $command->serialNumber,
        locationLabel: $command->locationLabel,
      );
    } catch (ValueError $exception) {
      throw InvalidValueException::because($exception->getMessage(), $exception);
    }

    $this->equipmentRepository->save($equipment);

    return new UpdateEquipmentResult(
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
        $this->tagRepository->findByEquipmentId($equipmentId),
      ),
      createdAt: $equipment->createdAt(),
      updatedAt: $equipment->updatedAt(),
      facilityName: $this->resolveFacilityName($equipment),
    );
  }

  /**
   * Method resolveFacilityName.
   *
   * @since 1.0.0
   *
   * @param Equipment $equipment the equipment aggregate
   *
   * @return ?string the assigned facility's display name, or null when unassigned or unresolved
   */
  private function resolveFacilityName(Equipment $equipment): ?string
  {
    $facilityId = $equipment->facilityId()?->__toString();

    if (null === $facilityId) {
      return null;
    }

    return $this->facilityNaming->findNamesByIds([$facilityId])[$facilityId] ?? null;
  }

  // #endregion
}
