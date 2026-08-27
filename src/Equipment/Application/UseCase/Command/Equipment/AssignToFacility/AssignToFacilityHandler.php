<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Command\Equipment\AssignToFacility;

use DateTimeImmutable;
use Equipment\Application\Port\Outbound\{EquipmentRepositoryPort, FacilityValidationPort, TagRepositoryPort};
use Equipment\Domain\Exception\EquipmentNotFoundException;
use Equipment\Domain\ValueObject\{EquipmentFacilityId, EquipmentId, EquipmentOrganizationId};
use Exception;
use Shared\Application\Message\CommandHandler;
use Shared\Domain\Exception\InvalidValueException;

use function array_map;
use function sprintf;

/**
 * UseCase AssignToFacilityHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AssignToFacilityHandler implements CommandHandler
{
  // #region Constructor
  public function __construct(
    private EquipmentRepositoryPort $equipmentRepository,
    private FacilityValidationPort $facilityValidation,
    private TagRepositoryPort $tagRepository,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   */
  public function __invoke(AssignToFacilityCommand $command): AssignToFacilityResult
  {
    $equipmentId = EquipmentId::fromString($command->equipmentId);
    $organizationId = EquipmentOrganizationId::fromString($command->organizationId);
    $facilityId = EquipmentFacilityId::fromString($command->facilityId);

    $equipment = $this->equipmentRepository->findById($equipmentId);

    if (null === $equipment || (string) $equipment->organizationId() !== (string) $organizationId) {
      throw EquipmentNotFoundException::withId($command->equipmentId);
    }

    $this->facilityValidation->assertFacilityIsAssignable($command->facilityId, $command->organizationId);

    try {
      $installedAt = null !== $command->installedAt
        ? new DateTimeImmutable($command->installedAt)
        : new DateTimeImmutable();
    } catch (Exception $dateException) {
      throw InvalidValueException::because(
        sprintf('Invalid date format for installedAt: "%s".', $command->installedAt),
        $dateException,
      );
    }

    $equipment->assignToFacility($facilityId, $installedAt);

    $this->equipmentRepository->save($equipment);

    $tags = $this->tagRepository->findByEquipmentId($equipmentId);

    return new AssignToFacilityResult(
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
    );
  }
  // #endregion
}
