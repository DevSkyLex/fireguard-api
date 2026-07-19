<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Command\Equipment\PutUnderMaintenance;

use Equipment\Application\Port\Outbound\{EquipmentRepositoryPort, MaintenanceLogRepositoryPort, TagRepositoryPort};
use Equipment\Domain\Event\Equipment\EquipmentPutUnderMaintenanceEvent;
use Equipment\Domain\Exception\EquipmentNotFoundException;
use Equipment\Domain\Model\MaintenanceLog\EquipmentMaintenanceLog;
use Equipment\Domain\ValueObject\{EquipmentId, EquipmentOrganizationId, EquipmentStatus, MaintenanceLogId};
use InvalidArgumentException;
use Notification\Application\Contract\Notification\{NotificationChannel, SendNotificationRequest};
use Notification\Application\Port\Inbound\NotificationPort;
use Notification\Domain\ValueObject\NotificationType;
use Organization\Application\Port\Outbound\OrganizationRepositoryPort;
use Organization\Domain\ValueObject\OrganizationId;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{EventDispatcherPort, LoggerPort};
use Shared\Domain\Exception\InvalidValueException;
use Throwable;

use function array_map;
use function sprintf;

/**
 * UseCase PutUnderMaintenanceHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PutUnderMaintenanceHandler implements CommandHandler
{
  // #region Constructor
  public function __construct(
    private EquipmentRepositoryPort $equipmentRepository,
    private TagRepositoryPort $tagRepository,
    private MaintenanceLogRepositoryPort $maintenanceLogRepository,
    private OrganizationRepositoryPort $organizationRepository,
    private NotificationPort $notificationPort,
    private LoggerPort $logger,
    private UuidFactory $uuidFactory,
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
  public function __invoke(PutUnderMaintenanceCommand $command): PutUnderMaintenanceResult
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

    $previousStatus = $equipment->status();
    $wasAlreadyUnderMaintenance = EquipmentStatus::UNDER_MAINTENANCE === $previousStatus;

    $equipment->putUnderMaintenance();

    $this->equipmentRepository->save($equipment);

    if (!$wasAlreadyUnderMaintenance) {
      // Emitted IMMEDIATELY after the durable equipment save (before the
      // fallible maintenance-log write): a transient log failure must not
      // leave the committed status transition permanently unrecorded — the
      // idempotent retry would skip this branch and never emit.
      $this->eventDispatcher->dispatch(new EquipmentPutUnderMaintenanceEvent(
        organizationId: (string) $organizationId,
        equipmentId: (string) $equipment->id(),
        facilityId: $equipment->facilityId()?->__toString(),
        previousStatus: $previousStatus->value,
      ));

      /** @var MaintenanceLogId $logId */
      $logId = $this->uuidFactory->create(MaintenanceLogId::class);
      $log = EquipmentMaintenanceLog::open($logId, $equipmentId, $organizationId);
      $this->maintenanceLogRepository->save($log);
    }

    $tags = $this->tagRepository->findByEquipmentId($equipmentId);

    if (!$wasAlreadyUnderMaintenance) {
      $organization = $this->organizationRepository->findById(new OrganizationId((string) $organizationId));
    } else {
      $organization = null;
    }

    if (null !== $organization) {
      $equipmentLabel = $equipment->locationLabel() ?? $equipment->type()->label();

      try {
        $this->notificationPort->send(new SendNotificationRequest(
          type: NotificationType::EQUIPMENT_UNDER_MAINTENANCE,
          subject: 'Equipment under maintenance',
          body: sprintf('%s is now under maintenance.', $equipmentLabel),
          channels: [NotificationChannel::MERCURE],
          payload: [
            'organizationId' => (string) $organizationId,
            'equipmentId' => (string) $equipment->id(),
            'facilityId' => $equipment->facilityId()?->__toString(),
            'equipmentType' => $equipment->type()->value,
            'equipmentLabel' => $equipmentLabel,
            'status' => $equipment->status()->value,
            'updatedAt' => $equipment->updatedAt()->format('c'),
          ],
          recipientUserId: $organization->ownerUserId(),
          organizationId: (string) $organizationId,
        ));
      } catch (Throwable $exception) {
        $this->logger->warning('Equipment under maintenance notification dispatch failed.', [
          'organizationId' => (string) $organizationId,
          'equipmentId' => (string) $equipment->id(),
          'recipientUserId' => $organization->ownerUserId(),
          'error' => $exception->getMessage(),
        ]);
      }
    }

    return new PutUnderMaintenanceResult(
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
