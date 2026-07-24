<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Command\Equipment\CommissionEquipment;

use Equipment\Application\Port\Outbound\{EquipmentRepositoryPort, MaintenanceLogRepositoryPort, TagRepositoryPort};
use Equipment\Domain\Event\Equipment\EquipmentCommissionedEvent;
use Equipment\Domain\Exception\EquipmentNotFoundException;
use Equipment\Domain\ValueObject\{EquipmentId, EquipmentOrganizationId, EquipmentStatus};
use InvalidArgumentException;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\EventDispatcherPort;
use Shared\Domain\Exception\InvalidValueException;

use function array_map;

/**
 * UseCase CommissionEquipmentHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CommissionEquipmentHandler implements CommandHandler
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
  public function __invoke(CommissionEquipmentCommand $command): CommissionEquipmentResult
  {
    try {
      $equipmentId = EquipmentId::fromString($command->equipmentId);
      $organizationId = EquipmentOrganizationId::fromString($command->organizationId);
    } catch (InvalidValueException $exception) {
      throw new InvalidArgumentException($exception->getMessage(), 0, $exception);
    }

    $equipment = $this->equipmentRepository->findPublishedById($equipmentId);

    if (null === $equipment || (string) $equipment->organizationId() !== (string) $organizationId) {
      throw EquipmentNotFoundException::withId($command->equipmentId);
    }

    $previousStatus = $equipment->status()->value;
    $wasUnderMaintenance = EquipmentStatus::UNDER_MAINTENANCE === $equipment->status();

    $equipment->commission();

    $this->equipmentRepository->save($equipment);

    if (EquipmentStatus::OPERATIONAL->value !== $previousStatus) {
      // Emitted IMMEDIATELY after the durable equipment save (before the
      // fallible maintenance-log close): a transient log failure must not
      // leave the committed status transition permanently unrecorded — the
      // idempotent retry would see an already-operational asset and never
      // emit. Re-commissioning an already-operational asset stays silent.
      $this->eventDispatcher->dispatch(new EquipmentCommissionedEvent(
        organizationId: (string) $equipment->organizationId(),
        equipmentId: (string) $equipment->id(),
        facilityId: null !== $equipment->facilityId() ? (string) $equipment->facilityId() : null,
        previousStatus: $previousStatus,
      ));
    }

    if ($wasUnderMaintenance) {
      $openLog = $this->maintenanceLogRepository->findOpenByEquipmentId($equipmentId);
      if (null !== $openLog) {
        $openLog->close();
        $this->maintenanceLogRepository->save($openLog);
      }
    }

    $tags = $this->tagRepository->findByEquipmentId($equipmentId);

    return new CommissionEquipmentResult(
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
