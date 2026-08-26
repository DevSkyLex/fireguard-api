<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Command\Equipment\DecommissionEquipment;

use Equipment\Application\Port\Outbound\{EquipmentRepositoryPort, MaintenanceLogRepositoryPort, TagRepositoryPort};
use Equipment\Domain\Event\Equipment\EquipmentDecommissionedEvent;
use Equipment\Domain\Exception\EquipmentNotFoundException;
use Equipment\Domain\ValueObject\{EquipmentId, EquipmentOrganizationId, EquipmentStatus};
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\EventDispatcherPort;

use function array_map;

/**
 * UseCase DecommissionEquipmentHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DecommissionEquipmentHandler implements CommandHandler
{
  // #region Constructor
  public function __construct(
    private EquipmentRepositoryPort $equipmentRepository,
    private TagRepositoryPort $tagRepository,
    private MaintenanceLogRepositoryPort $maintenanceLogRepository,
    private EventDispatcherPort $eventDispatcher,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   */
  public function __invoke(DecommissionEquipmentCommand $command): DecommissionEquipmentResult
  {
    $equipmentId = EquipmentId::fromString($command->equipmentId);
    $organizationId = EquipmentOrganizationId::fromString($command->organizationId);

    $equipment = $this->equipmentRepository->findPublishedById($equipmentId);

    if (null === $equipment || (string) $equipment->organizationId() !== (string) $organizationId) {
      throw EquipmentNotFoundException::withId($command->equipmentId);
    }

    $wasUnderMaintenance = EquipmentStatus::UNDER_MAINTENANCE === $equipment->status();
    $previousStatus = $equipment->status()->value;

    $equipment->decommission();

    $this->equipmentRepository->save($equipment);

    // Emitted after the durable save so a failed persistence leaves no ledger
    // row; the already-decommissioned path throws before reaching this point.
    $this->eventDispatcher->dispatch(new EquipmentDecommissionedEvent(
      organizationId: (string) $equipment->organizationId(),
      equipmentId: (string) $equipment->id(),
      previousStatus: $previousStatus,
    ));

    // Decommissioning an item mid-maintenance closes its open maintenance log, so
    // no log is left "in progress" forever on a retired asset.
    if ($wasUnderMaintenance) {
      $openLog = $this->maintenanceLogRepository->findOpenByEquipmentId($equipmentId);
      if (null !== $openLog) {
        $openLog->close();
        $this->maintenanceLogRepository->save($openLog);
      }
    }

    $tags = $this->tagRepository->findByEquipmentId($equipmentId);

    return new DecommissionEquipmentResult(
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
