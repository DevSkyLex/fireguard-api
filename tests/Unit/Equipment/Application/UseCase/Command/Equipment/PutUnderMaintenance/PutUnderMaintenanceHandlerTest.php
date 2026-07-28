<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Application\UseCase\Command\Equipment\PutUnderMaintenance;

use DateTimeImmutable;
use Equipment\Application\Port\Outbound\{EquipmentRepositoryPort, MaintenanceLogRepositoryPort, TagRepositoryPort};
use Equipment\Application\UseCase\Command\Equipment\PutUnderMaintenance\{PutUnderMaintenanceCommand, PutUnderMaintenanceHandler, PutUnderMaintenanceResult};
use Equipment\Domain\Event\Equipment\EquipmentPutUnderMaintenanceEvent;
use Equipment\Domain\Exception\{EquipmentAlreadyDecommissionedException, EquipmentNotFoundException};
use Equipment\Domain\Model\Equipment\Equipment;
use Equipment\Domain\ValueObject\{EquipmentFacilityId, EquipmentId, EquipmentOrganizationId, EquipmentType, MaintenanceLogId};
use InvalidArgumentException;
use Notification\Application\Contract\Notification\{NotificationChannel, SendNotificationRequest, SentNotification};
use Notification\Application\Port\Inbound\NotificationPort;
use Notification\Domain\ValueObject\NotificationType;
use Organization\Application\Port\Outbound\OrganizationRepositoryPort;
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationName};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\{EventDispatcherPort, LoggerPort};

use function is_string;

#[CoversClass(PutUnderMaintenanceHandler::class)]
final class PutUnderMaintenanceHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655442001';

  private const string EQUIP_ID = '550e8400-e29b-41d4-a716-446655442002';

  private const string FACILITY_ID = '550e8400-e29b-41d4-a716-446655442003';

  // #region Methods
  #[Test]
  public function testInvokePutsEquipmentUnderMaintenance(): void
  {
    $equipment = Equipment::create(
      id: EquipmentId::fromString(self::EQUIP_ID),
      organizationId: EquipmentOrganizationId::fromString(self::ORG_ID),
      type: EquipmentType::FIRE_EXTINGUISHER,
    );
    $equipment->assignToFacility(
      EquipmentFacilityId::fromString(self::FACILITY_ID),
      new DateTimeImmutable(),
    );
    $equipment->commission();

    /** @var EquipmentRepositoryPort&MockObject $equipmentRepository */
    $equipmentRepository = $this->createMock(EquipmentRepositoryPort::class);
    $equipmentRepository->expects(self::once())
      ->method('findPublishedById')
      ->willReturn($equipment);
    $equipmentRepository->expects(self::once())->method('save');

    $tagRepository = $this->createStub(TagRepositoryPort::class);
    $tagRepository->method('findByEquipmentId')->willReturn([]);

    /** @var MaintenanceLogRepositoryPort&MockObject $maintenanceLogRepository */
    $maintenanceLogRepository = $this->createMock(MaintenanceLogRepositoryPort::class);
    $maintenanceLogRepository->expects(self::once())->method('save');

    $organization = Organization::reconstitute(
      id: new OrganizationId(self::ORG_ID),
      name: new OrganizationName('Fireguard HQ'),
      createdByUserId: '550e8400-e29b-41d4-a716-446655442011',
      isActive: true,
      createdAt: new DateTimeImmutable('-2 days'),
      updatedAt: new DateTimeImmutable('-1 day'),
      ownerUserId: '550e8400-e29b-41d4-a716-446655442011',
    );

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturn($organization);

    /** @var NotificationPort&MockObject $notificationPort */
    $notificationPort = $this->createMock(NotificationPort::class);
    $notificationPort->expects(self::once())
      ->method('send')
      ->with(self::callback(static function (SendNotificationRequest $request): bool {
        return NotificationType::EQUIPMENT_UNDER_MAINTENANCE === $request->type
          && 'Equipment under maintenance' === $request->subject
          && 'Fire Extinguisher is now under maintenance.' === $request->body
          && [NotificationChannel::MERCURE] === $request->channels
          && self::ORG_ID === ($request->payload['organizationId'] ?? null)
          && self::EQUIP_ID === ($request->payload['equipmentId'] ?? null)
          && self::FACILITY_ID === ($request->payload['facilityId'] ?? null)
          && 'fire_extinguisher' === ($request->payload['equipmentType'] ?? null)
          && 'Fire Extinguisher' === ($request->payload['equipmentLabel'] ?? null)
          && 'under_maintenance' === ($request->payload['status'] ?? null)
          && is_string($request->payload['updatedAt'] ?? null)
          && '550e8400-e29b-41d4-a716-446655442011' === $request->recipientUserId
          && self::ORG_ID === $request->organizationId;
      }))
      ->willReturn(new SentNotification(
        id: '550e8400-e29b-41d4-a716-446655449040',
        type: NotificationType::EQUIPMENT_UNDER_MAINTENANCE,
        subject: 'Equipment under maintenance',
        body: 'Fire Extinguisher is now under maintenance.',
        channels: [NotificationChannel::MERCURE->value],
        payload: ['organizationId' => self::ORG_ID],
        channelDelivery: [NotificationChannel::MERCURE->value => true],
        createdAt: new DateTimeImmutable('2024-01-01T00:00:00+00:00'),
        recipientUserId: '550e8400-e29b-41d4-a716-446655442011',
      ));

    /** @var LoggerPort&MockObject $logger */
    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::never())->method('warning');

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('create')
      ->willReturn(MaintenanceLogId::fromString('550e8400-e29b-41d4-a716-446655442010'));

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (object $event): bool {
        return $event instanceof EquipmentPutUnderMaintenanceEvent
          && self::ORG_ID === $event->organizationId
          && self::EQUIP_ID === $event->equipmentId
          && self::FACILITY_ID === $event->facilityId
          && 'operational' === $event->previousStatus;
      }));

    $handler = new PutUnderMaintenanceHandler(
      equipmentRepository: $equipmentRepository,
      tagRepository: $tagRepository,
      maintenanceLogRepository: $maintenanceLogRepository,
      organizationRepository: $organizationRepository,
      notificationPort: $notificationPort,
      logger: $logger,
      uuidFactory: $uuidFactory,
      eventDispatcher: $eventDispatcher,
    );

    $result = $handler->__invoke(new PutUnderMaintenanceCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
    ));

    self::assertInstanceOf(PutUnderMaintenanceResult::class, $result);
    self::assertSame('under_maintenance', $result->status);
  }

  #[Test]
  public function testInvokeThrowsWhenEquipmentNotFound(): void
  {
    // findPublishedById also returns null for draft intervention
    // scratchpads, so this path covers both "missing" and "draft":
    // neither may be mutated nor audited.
    /** @var EquipmentRepositoryPort&MockObject $equipmentRepository */
    $equipmentRepository = $this->createMock(EquipmentRepositoryPort::class);
    $equipmentRepository->method('findPublishedById')->willReturn(null);
    $equipmentRepository->expects(self::never())->method('save');

    $tagRepository = $this->createStub(TagRepositoryPort::class);

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::never())->method('findById');

    /** @var NotificationPort&MockObject $notificationPort */
    $notificationPort = $this->createMock(NotificationPort::class);
    $notificationPort->expects(self::never())->method('send');

    /** @var LoggerPort&MockObject $logger */
    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::never())->method('warning');

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new PutUnderMaintenanceHandler(
      equipmentRepository: $equipmentRepository,
      tagRepository: $tagRepository,
      maintenanceLogRepository: $this->createStub(MaintenanceLogRepositoryPort::class),
      organizationRepository: $organizationRepository,
      notificationPort: $notificationPort,
      logger: $logger,
      uuidFactory: $this->createStub(UuidFactory::class),
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(EquipmentNotFoundException::class);

    $handler->__invoke(new PutUnderMaintenanceCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenEquipmentIsDecommissioned(): void
  {
    $equipment = Equipment::create(
      id: EquipmentId::fromString(self::EQUIP_ID),
      organizationId: EquipmentOrganizationId::fromString(self::ORG_ID),
      type: EquipmentType::FIRE_EXTINGUISHER,
    );
    $equipment->decommission();

    /** @var EquipmentRepositoryPort&MockObject $equipmentRepository */
    $equipmentRepository = $this->createMock(EquipmentRepositoryPort::class);
    $equipmentRepository->method('findPublishedById')->willReturn($equipment);
    $equipmentRepository->expects(self::never())->method('save');

    $tagRepository = $this->createStub(TagRepositoryPort::class);

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::never())->method('findById');

    /** @var NotificationPort&MockObject $notificationPort */
    $notificationPort = $this->createMock(NotificationPort::class);
    $notificationPort->expects(self::never())->method('send');

    /** @var LoggerPort&MockObject $logger */
    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::never())->method('warning');

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new PutUnderMaintenanceHandler(
      equipmentRepository: $equipmentRepository,
      tagRepository: $tagRepository,
      maintenanceLogRepository: $this->createStub(MaintenanceLogRepositoryPort::class),
      organizationRepository: $organizationRepository,
      notificationPort: $notificationPort,
      logger: $logger,
      uuidFactory: $this->createStub(UuidFactory::class),
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(EquipmentAlreadyDecommissionedException::class);

    $handler->__invoke(new PutUnderMaintenanceCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenEquipmentIsInStock(): void
  {
    // An in-stock (never commissioned) equipment cannot go straight into
    // maintenance — it must be commissioned first (operational ↔ maintenance).
    $equipment = Equipment::create(
      id: EquipmentId::fromString(self::EQUIP_ID),
      organizationId: EquipmentOrganizationId::fromString(self::ORG_ID),
      type: EquipmentType::FIRE_EXTINGUISHER,
    );

    /** @var EquipmentRepositoryPort&MockObject $equipmentRepository */
    $equipmentRepository = $this->createMock(EquipmentRepositoryPort::class);
    $equipmentRepository->method('findPublishedById')->willReturn($equipment);
    $equipmentRepository->expects(self::never())->method('save');

    $tagRepository = $this->createStub(TagRepositoryPort::class);

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::never())->method('findById');

    /** @var NotificationPort&MockObject $notificationPort */
    $notificationPort = $this->createMock(NotificationPort::class);
    $notificationPort->expects(self::never())->method('send');

    /** @var LoggerPort&MockObject $logger */
    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::never())->method('warning');

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new PutUnderMaintenanceHandler(
      equipmentRepository: $equipmentRepository,
      tagRepository: $tagRepository,
      maintenanceLogRepository: $this->createStub(MaintenanceLogRepositoryPort::class),
      organizationRepository: $organizationRepository,
      notificationPort: $notificationPort,
      logger: $logger,
      uuidFactory: $this->createStub(UuidFactory::class),
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('In-stock equipment must be commissioned before it can be put under maintenance.');

    $handler->__invoke(new PutUnderMaintenanceCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
    ));
  }

  #[Test]
  public function testInvokeDoesNotCreateNewLogWhenAlreadyUnderMaintenance(): void
  {
    $equipment = Equipment::create(
      id: EquipmentId::fromString(self::EQUIP_ID),
      organizationId: EquipmentOrganizationId::fromString(self::ORG_ID),
      type: EquipmentType::FIRE_EXTINGUISHER,
    );
    $equipment->assignToFacility(
      EquipmentFacilityId::fromString(self::FACILITY_ID),
      new DateTimeImmutable(),
    );
    $equipment->commission();
    $equipment->putUnderMaintenance();

    /** @var EquipmentRepositoryPort&MockObject $equipmentRepository */
    $equipmentRepository = $this->createMock(EquipmentRepositoryPort::class);
    $equipmentRepository->expects(self::once())
      ->method('findPublishedById')
      ->willReturn($equipment);
    $equipmentRepository->expects(self::once())->method('save');

    $tagRepository = $this->createStub(TagRepositoryPort::class);
    $tagRepository->method('findByEquipmentId')->willReturn([]);

    /** @var MaintenanceLogRepositoryPort&MockObject $maintenanceLogRepository */
    $maintenanceLogRepository = $this->createMock(MaintenanceLogRepositoryPort::class);
    $maintenanceLogRepository->expects(self::never())->method('save');

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::never())->method('findById');

    /** @var NotificationPort&MockObject $notificationPort */
    $notificationPort = $this->createMock(NotificationPort::class);
    $notificationPort->expects(self::never())->method('send');

    /** @var LoggerPort&MockObject $logger */
    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::never())->method('warning');

    // Idempotent repeat: the status did not change, so no audit event.
    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new PutUnderMaintenanceHandler(
      equipmentRepository: $equipmentRepository,
      tagRepository: $tagRepository,
      maintenanceLogRepository: $maintenanceLogRepository,
      organizationRepository: $organizationRepository,
      notificationPort: $notificationPort,
      logger: $logger,
      uuidFactory: $this->createStub(UuidFactory::class),
      eventDispatcher: $eventDispatcher,
    );

    $result = $handler->__invoke(new PutUnderMaintenanceCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
    ));

    self::assertSame('under_maintenance', $result->status);
  }

  #[Test]
  public function testInvokeReturnsResultWhenNotificationDispatchFails(): void
  {
    $equipment = Equipment::create(
      id: EquipmentId::fromString(self::EQUIP_ID),
      organizationId: EquipmentOrganizationId::fromString(self::ORG_ID),
      type: EquipmentType::FIRE_EXTINGUISHER,
    );
    $equipment->assignToFacility(
      EquipmentFacilityId::fromString(self::FACILITY_ID),
      new DateTimeImmutable(),
    );
    $equipment->commission();

    /** @var EquipmentRepositoryPort&MockObject $equipmentRepository */
    $equipmentRepository = $this->createMock(EquipmentRepositoryPort::class);
    $equipmentRepository->expects(self::once())
      ->method('findPublishedById')
      ->willReturn($equipment);
    $equipmentRepository->expects(self::once())->method('save');

    $tagRepository = $this->createStub(TagRepositoryPort::class);
    $tagRepository->method('findByEquipmentId')->willReturn([]);

    /** @var MaintenanceLogRepositoryPort&MockObject $maintenanceLogRepository */
    $maintenanceLogRepository = $this->createMock(MaintenanceLogRepositoryPort::class);
    $maintenanceLogRepository->expects(self::once())->method('save');

    $organization = Organization::reconstitute(
      id: new OrganizationId(self::ORG_ID),
      name: new OrganizationName('Fireguard HQ'),
      createdByUserId: '550e8400-e29b-41d4-a716-446655442012',
      isActive: true,
      createdAt: new DateTimeImmutable('-2 days'),
      updatedAt: new DateTimeImmutable('-1 day'),
      ownerUserId: '550e8400-e29b-41d4-a716-446655442012',
    );

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturn($organization);

    /** @var NotificationPort&MockObject $notificationPort */
    $notificationPort = $this->createMock(NotificationPort::class);
    $notificationPort->expects(self::once())
      ->method('send')
      ->willThrowException(new RuntimeException('Mercure hub unavailable.'));

    /** @var LoggerPort&MockObject $logger */
    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::once())
      ->method('warning')
      ->with(
        'Equipment under maintenance notification dispatch failed.',
        [
          'organizationId' => self::ORG_ID,
          'equipmentId' => self::EQUIP_ID,
          'recipientUserId' => '550e8400-e29b-41d4-a716-446655442012',
          'error' => 'Mercure hub unavailable.',
        ],
      );

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('create')
      ->willReturn(MaintenanceLogId::fromString('550e8400-e29b-41d4-a716-446655442010'));

    // The audit event follows the persisted state change and must not be
    // held back by a best-effort notification failure.
    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (object $event): bool {
        return $event instanceof EquipmentPutUnderMaintenanceEvent
          && self::ORG_ID === $event->organizationId
          && self::EQUIP_ID === $event->equipmentId
          && self::FACILITY_ID === $event->facilityId
          && 'operational' === $event->previousStatus;
      }));

    $handler = new PutUnderMaintenanceHandler(
      equipmentRepository: $equipmentRepository,
      tagRepository: $tagRepository,
      maintenanceLogRepository: $maintenanceLogRepository,
      organizationRepository: $organizationRepository,
      notificationPort: $notificationPort,
      logger: $logger,
      uuidFactory: $uuidFactory,
      eventDispatcher: $eventDispatcher,
    );

    $result = $handler->__invoke(new PutUnderMaintenanceCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
    ));

    self::assertInstanceOf(PutUnderMaintenanceResult::class, $result);
    self::assertSame('under_maintenance', $result->status);
  }

  #[Test]
  public function testInvokeThrowsInvalidArgumentForMalformedIdentifiers(): void
  {
    /** @var EquipmentRepositoryPort&MockObject $equipmentRepository */
    $equipmentRepository = $this->createMock(EquipmentRepositoryPort::class);
    $equipmentRepository->expects(self::never())->method('findPublishedById');

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new PutUnderMaintenanceHandler(
      equipmentRepository: $equipmentRepository,
      tagRepository: $this->createStub(TagRepositoryPort::class),
      maintenanceLogRepository: $this->createStub(MaintenanceLogRepositoryPort::class),
      organizationRepository: $this->createStub(OrganizationRepositoryPort::class),
      notificationPort: $this->createStub(NotificationPort::class),
      logger: $this->createStub(LoggerPort::class),
      uuidFactory: $this->createStub(UuidFactory::class),
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(InvalidArgumentException::class);

    $handler->__invoke(new PutUnderMaintenanceCommand(
      organizationId: 'not-a-uuid',
      equipmentId: 'also-not-a-uuid',
    ));
  }
  // #endregion
}
