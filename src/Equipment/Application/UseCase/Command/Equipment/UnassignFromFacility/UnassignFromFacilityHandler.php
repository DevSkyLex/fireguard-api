<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Command\Equipment\UnassignFromFacility;

use Equipment\Application\Port\Outbound\{EquipmentRepositoryPort, TagRepositoryPort};
use Equipment\Domain\Exception\EquipmentNotFoundException;
use Equipment\Domain\ValueObject\{EquipmentId, EquipmentOrganizationId};
use InvalidArgumentException;
use Shared\Application\Message\CommandHandler;
use Shared\Domain\Exception\InvalidValueException;

use function array_map;

/**
 * UseCase UnassignFromFacilityHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UnassignFromFacilityHandler implements CommandHandler
{
  // #region Constructor
  public function __construct(
    private EquipmentRepositoryPort $equipmentRepository,
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
  public function __invoke(UnassignFromFacilityCommand $command): UnassignFromFacilityResult
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

    $equipment->unassignFromFacility();

    $this->equipmentRepository->save($equipment);

    $tags = $this->tagRepository->findByEquipmentId($equipmentId);

    return new UnassignFromFacilityResult(
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
