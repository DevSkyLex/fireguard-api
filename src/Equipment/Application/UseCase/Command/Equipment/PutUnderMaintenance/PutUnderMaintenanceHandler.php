<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Command\Equipment\PutUnderMaintenance;

use Equipment\Application\Port\Outbound\{EquipmentRepositoryPort, FacilityNamingPort, MaintenanceLogRepositoryPort, TagRepositoryPort};
use Equipment\Domain\Event\Equipment\EquipmentPutUnderMaintenanceEvent;
use Equipment\Domain\Exception\EquipmentNotFoundException;
use Equipment\Domain\Model\Equipment\Equipment;
use Equipment\Domain\Model\MaintenanceLog\EquipmentMaintenanceLog;
use Equipment\Domain\ValueObject\{EquipmentId, EquipmentOrganizationId, EquipmentStatus, MaintenanceLogId};
use Notification\Application\Contract\Notification\{NotificationChannel, SendNotificationRequest};
use Notification\Application\Contract\Notification\NotificationType;
use Notification\Application\Port\Inbound\NotificationPort;
use Organization\Application\Port\Outbound\OrganizationRepositoryPort;
use Organization\Domain\ValueObject\OrganizationId;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{EventDispatcherPort, LoggerPort};
use Throwable;

use function array_map;
use function sprintf;

/**
 * UseCase PutUnderMaintenanceHandler.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PutUnderMaintenanceHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Receives equipment, organization, notification, logging, identifier, and event capabilities needed to record the maintenance transition.
   *
   * @access public
   *
   * @param EquipmentRepositoryPort $equipmentRepository port used to load and persist equipment state
   * @param TagRepositoryPort $tagRepository port used by equipment status handling
   * @param MaintenanceLogRepositoryPort $maintenanceLogRepository port used to record the maintenance lifecycle change
   * @param FacilityNamingPort $facilityNaming port used to resolve the facility label for the change
   * @param OrganizationRepositoryPort $organizationRepository port used to load organization notification context
   * @param NotificationPort $notificationPort port used to notify relevant users about the transition
   * @param LoggerPort $logger port used to record operational failures
   * @param UuidFactory $uuidFactory factory used to identify generated maintenance records
   * @param EventDispatcherPort $eventDispatcher port used to publish the committed equipment event
   *
   * @return void
   */
  public function __construct(
    private EquipmentRepositoryPort $equipmentRepository,
    private TagRepositoryPort $tagRepository,
    private MaintenanceLogRepositoryPort $maintenanceLogRepository,
    private FacilityNamingPort $facilityNaming,
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
    $equipmentId = EquipmentId::fromString($command->equipmentId);
    $organizationId = EquipmentOrganizationId::fromString($command->organizationId);

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
      name: $equipment->identity()->name,
      assetCode: $equipment->identity()->assetCode,
      criticality: $equipment->identity()->criticality,
      technicalProperties: $equipment->identity()->technicalProperties,
      predecessorEquipmentId: $equipment->predecessorEquipmentId(),
      successorEquipmentId: $equipment->successorEquipmentId(),
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
