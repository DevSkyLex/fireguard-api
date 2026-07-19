<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Application\UseCase\Command\Equipment\DecommissionEquipment;

use DateTimeImmutable;
use Equipment\Application\Port\Outbound\{EquipmentRepositoryPort, MaintenanceLogRepositoryPort, TagRepositoryPort};
use Equipment\Application\UseCase\Command\Equipment\DecommissionEquipment\{DecommissionEquipmentCommand, DecommissionEquipmentHandler, DecommissionEquipmentResult};
use Equipment\Domain\Event\Equipment\EquipmentDecommissionedEvent;
use Equipment\Domain\Exception\{EquipmentAlreadyDecommissionedException, EquipmentNotFoundException};
use Equipment\Domain\Model\Equipment\Equipment;
use Equipment\Domain\Model\MaintenanceLog\EquipmentMaintenanceLog;
use Equipment\Domain\ValueObject\{EquipmentFacilityId, EquipmentId, EquipmentOrganizationId, EquipmentType, MaintenanceLogId};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Outbound\EventDispatcherPort;

#[CoversClass(DecommissionEquipmentHandler::class)]
final class DecommissionEquipmentHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655441001';

  private const string EQUIP_ID = '550e8400-e29b-41d4-a716-446655441002';

  // #region Methods
  #[Test]
  public function testInvokeDecommissionsEquipment(): void
  {
    $equipment = Equipment::create(
      id: EquipmentId::fromString(self::EQUIP_ID),
      organizationId: EquipmentOrganizationId::fromString(self::ORG_ID),
      type: EquipmentType::FIRE_EXTINGUISHER,
    );

    /** @var EquipmentRepositoryPort&MockObject $equipmentRepository */
    $equipmentRepository = $this->createMock(EquipmentRepositoryPort::class);
    $equipmentRepository->expects(self::once())
      ->method('findPublishedById')
      ->willReturn($equipment);
    $equipmentRepository->expects(self::once())->method('save');

    $tagRepository = $this->createStub(TagRepositoryPort::class);
    $tagRepository->method('findByEquipmentId')->willReturn([]);

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (EquipmentDecommissionedEvent $event): bool {
        return self::ORG_ID === $event->organizationId
          && self::EQUIP_ID === $event->equipmentId
          && 'in_stock' === $event->previousStatus;
      }));

    $handler = new DecommissionEquipmentHandler(
      equipmentRepository: $equipmentRepository,
      tagRepository: $tagRepository,
      maintenanceLogRepository: $this->createStub(MaintenanceLogRepositoryPort::class),
      eventDispatcher: $eventDispatcher,
    );

    $result = $handler->__invoke(new DecommissionEquipmentCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
    ));

    self::assertInstanceOf(DecommissionEquipmentResult::class, $result);
    self::assertSame('decommissioned', $result->status);
  }

  #[Test]
  public function testInvokeThrowsWhenEquipmentNotFound(): void
  {
    // findPublishedById also hides draft intervention scratchpads, so an
    // equipment that only exists inside an unpublished intervention lands
    // on this exact path: not found, no save, no audit event.
    /** @var EquipmentRepositoryPort&MockObject $equipmentRepository */
    $equipmentRepository = $this->createMock(EquipmentRepositoryPort::class);
    $equipmentRepository->method('findPublishedById')->willReturn(null);
    $equipmentRepository->expects(self::never())->method('save');

    $tagRepository = $this->createStub(TagRepositoryPort::class);

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new DecommissionEquipmentHandler(
      equipmentRepository: $equipmentRepository,
      tagRepository: $tagRepository,
      maintenanceLogRepository: $this->createStub(MaintenanceLogRepositoryPort::class),
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(EquipmentNotFoundException::class);

    $handler->__invoke(new DecommissionEquipmentCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenEquipmentBelongsToAnotherOrganization(): void
  {
    $equipment = Equipment::create(
      id: EquipmentId::fromString(self::EQUIP_ID),
      organizationId: EquipmentOrganizationId::fromString('550e8400-e29b-41d4-a716-446655441099'),
      type: EquipmentType::FIRE_EXTINGUISHER,
    );

    /** @var EquipmentRepositoryPort&MockObject $equipmentRepository */
    $equipmentRepository = $this->createMock(EquipmentRepositoryPort::class);
    $equipmentRepository->method('findPublishedById')->willReturn($equipment);
    $equipmentRepository->expects(self::never())->method('save');

    $tagRepository = $this->createStub(TagRepositoryPort::class);

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new DecommissionEquipmentHandler(
      equipmentRepository: $equipmentRepository,
      tagRepository: $tagRepository,
      maintenanceLogRepository: $this->createStub(MaintenanceLogRepositoryPort::class),
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(EquipmentNotFoundException::class);

    $handler->__invoke(new DecommissionEquipmentCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenAlreadyDecommissioned(): void
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

    // The idempotent repeat throws before save: no state change, no event.
    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new DecommissionEquipmentHandler(
      equipmentRepository: $equipmentRepository,
      tagRepository: $tagRepository,
      maintenanceLogRepository: $this->createStub(MaintenanceLogRepositoryPort::class),
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(EquipmentAlreadyDecommissionedException::class);

    $handler->__invoke(new DecommissionEquipmentCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
    ));
  }

  #[Test]
  public function testInvokeDispatchesNothingWhenSaveFails(): void
  {
    $equipment = Equipment::create(
      id: EquipmentId::fromString(self::EQUIP_ID),
      organizationId: EquipmentOrganizationId::fromString(self::ORG_ID),
      type: EquipmentType::FIRE_EXTINGUISHER,
    );

    /** @var EquipmentRepositoryPort&MockObject $equipmentRepository */
    $equipmentRepository = $this->createMock(EquipmentRepositoryPort::class);
    $equipmentRepository->method('findPublishedById')->willReturn($equipment);
    $equipmentRepository->expects(self::once())
      ->method('save')
      ->willThrowException(new RuntimeException('persistence failure'));

    $tagRepository = $this->createStub(TagRepositoryPort::class);

    // The event is emitted post-save only: a failed persistence must leave
    // no ledger row behind.
    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new DecommissionEquipmentHandler(
      equipmentRepository: $equipmentRepository,
      tagRepository: $tagRepository,
      maintenanceLogRepository: $this->createStub(MaintenanceLogRepositoryPort::class),
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(RuntimeException::class);

    $handler->__invoke(new DecommissionEquipmentCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
    ));
  }

  #[Test]
  public function testInvokeClosesOpenMaintenanceLogWhenDecommissioningUnderMaintenanceEquipment(): void
  {
    $equipment = Equipment::create(
      id: EquipmentId::fromString(self::EQUIP_ID),
      organizationId: EquipmentOrganizationId::fromString(self::ORG_ID),
      type: EquipmentType::FIRE_EXTINGUISHER,
    );
    $equipment->assignToFacility(
      EquipmentFacilityId::fromString('550e8400-e29b-41d4-a716-446655441003'),
      new DateTimeImmutable(),
    );
    $equipment->commission();
    $equipment->putUnderMaintenance();

    $openLog = EquipmentMaintenanceLog::open(
      MaintenanceLogId::fromString('550e8400-e29b-41d4-a716-446655441004'),
      EquipmentId::fromString(self::EQUIP_ID),
      EquipmentOrganizationId::fromString(self::ORG_ID),
    );

    /** @var EquipmentRepositoryPort&MockObject $equipmentRepository */
    $equipmentRepository = $this->createMock(EquipmentRepositoryPort::class);
    $equipmentRepository->method('findPublishedById')->willReturn($equipment);
    $equipmentRepository->expects(self::once())->method('save');

    $tagRepository = $this->createStub(TagRepositoryPort::class);
    $tagRepository->method('findByEquipmentId')->willReturn([]);

    /** @var MaintenanceLogRepositoryPort&MockObject $maintenanceLogRepository */
    $maintenanceLogRepository = $this->createMock(MaintenanceLogRepositoryPort::class);
    $maintenanceLogRepository->expects(self::once())
      ->method('findOpenByEquipmentId')
      ->willReturn($openLog);
    $maintenanceLogRepository->expects(self::once())
      ->method('save')
      ->with(self::identicalTo($openLog));

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (EquipmentDecommissionedEvent $event): bool {
        return self::ORG_ID === $event->organizationId
          && self::EQUIP_ID === $event->equipmentId
          && 'under_maintenance' === $event->previousStatus;
      }));

    $handler = new DecommissionEquipmentHandler(
      equipmentRepository: $equipmentRepository,
      tagRepository: $tagRepository,
      maintenanceLogRepository: $maintenanceLogRepository,
      eventDispatcher: $eventDispatcher,
    );

    $result = $handler->__invoke(new DecommissionEquipmentCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
    ));

    self::assertSame('decommissioned', $result->status);
  }
  // #endregion
}
