<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Application\UseCase\Query\Equipment\GetEquipment;

use DateTimeImmutable;
use Equipment\Application\Port\Outbound\{EquipmentRepositoryPort, MaintenanceDueStatusPort, TagRepositoryPort};
use Equipment\Application\Port\Outbound\FacilityNamingPort;
use Equipment\Application\UseCase\Query\Equipment\GetEquipment\{GetEquipmentHandler, GetEquipmentQuery, GetEquipmentResult};
use Equipment\Domain\Exception\EquipmentNotFoundException;
use Equipment\Domain\Model\Equipment\Equipment;
use Equipment\Domain\ValueObject\{EquipmentId, EquipmentOrganizationId, EquipmentType};
use Maintenance\Application\Contract\Plan\MaintenanceEquipmentOperationsDue;
use Maintenance\Application\Port\Inbound\MaintenanceOperationsDuePort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

#[CoversClass(GetEquipmentHandler::class)]
final class GetEquipmentHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655447001';

  private const string EQUIP_ID = '550e8400-e29b-41d4-a716-446655447002';

  #[Test]
  public function testInvokeThrowsInvalidArgumentOnInvalidEquipmentId(): void
  {
    /** @var EquipmentRepositoryPort&MockObject $equipmentRepository */
    $equipmentRepository = $this->createMock(EquipmentRepositoryPort::class);
    $equipmentRepository->expects(self::never())->method('findById');

    $handler = new GetEquipmentHandler(
      equipmentRepository: $equipmentRepository,
      tagRepository: $this->createStub(TagRepositoryPort::class),
      maintenanceDueStatusPort: $this->createStub(MaintenanceDueStatusPort::class),
      facilityNaming: $this->createStub(FacilityNamingPort::class),
    );

    $this->expectException(InvalidValueException::class);

    $handler->__invoke(new GetEquipmentQuery(
      organizationId: self::ORG_ID,
      equipmentId: 'not-a-uuid',
    ));
  }

  #[Test]
  public function testInvokeThrowsEquipmentNotFoundWhenRepositoryReturnsNull(): void
  {
    /** @var EquipmentRepositoryPort&MockObject $equipmentRepository */
    $equipmentRepository = $this->createMock(EquipmentRepositoryPort::class);
    $equipmentRepository->expects(self::once())
      ->method('findById')
      ->willReturn(null);

    $handler = new GetEquipmentHandler(
      equipmentRepository: $equipmentRepository,
      tagRepository: $this->createStub(TagRepositoryPort::class),
      maintenanceDueStatusPort: $this->createStub(MaintenanceDueStatusPort::class),
      facilityNaming: $this->createStub(FacilityNamingPort::class),
    );

    $this->expectException(EquipmentNotFoundException::class);

    $handler->__invoke(new GetEquipmentQuery(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsEquipmentNotFoundWhenOrganizationMismatch(): void
  {
    $equipment = Equipment::create(
      id: EquipmentId::fromString(self::EQUIP_ID),
      organizationId: EquipmentOrganizationId::fromString(self::ORG_ID),
      type: EquipmentType::FIRE_EXTINGUISHER,
    );

    /** @var EquipmentRepositoryPort&MockObject $equipmentRepository */
    $equipmentRepository = $this->createMock(EquipmentRepositoryPort::class);
    $equipmentRepository->expects(self::once())
      ->method('findById')
      ->willReturn($equipment);

    $handler = new GetEquipmentHandler(
      equipmentRepository: $equipmentRepository,
      tagRepository: $this->createStub(TagRepositoryPort::class),
      maintenanceDueStatusPort: $this->createStub(MaintenanceDueStatusPort::class),
      facilityNaming: $this->createStub(FacilityNamingPort::class),
    );

    $this->expectException(EquipmentNotFoundException::class);

    $handler->__invoke(new GetEquipmentQuery(
      organizationId: '550e8400-e29b-41d4-a716-446655447999',
      equipmentId: self::EQUIP_ID,
    ));
  }

  #[Test]
  public function testInvokeReturnsResultOnSuccess(): void
  {
    $equipment = Equipment::create(
      id: EquipmentId::fromString(self::EQUIP_ID),
      organizationId: EquipmentOrganizationId::fromString(self::ORG_ID),
      type: EquipmentType::FIRE_EXTINGUISHER,
    );

    /** @var EquipmentRepositoryPort&MockObject $equipmentRepository */
    $equipmentRepository = $this->createMock(EquipmentRepositoryPort::class);
    $equipmentRepository->expects(self::once())
      ->method('findById')
      ->willReturn($equipment);

    /** @var TagRepositoryPort&MockObject $tagRepository */
    $tagRepository = $this->createMock(TagRepositoryPort::class);
    $tagRepository->expects(self::once())
      ->method('findByEquipmentId')
      ->willReturn([]);

    /** @var MaintenanceDueStatusPort&MockObject $maintenanceDueStatusPort */
    $maintenanceDueStatusPort = $this->createMock(MaintenanceDueStatusPort::class);
    $maintenanceDueStatusPort->expects(self::once())
      ->method('dueStatusesForEquipment')
      ->with(self::ORG_ID, [self::EQUIP_ID])
      ->willReturn([self::EQUIP_ID => 'due_soon']);

    $handler = new GetEquipmentHandler(
      equipmentRepository: $equipmentRepository,
      tagRepository: $tagRepository,
      maintenanceDueStatusPort: $maintenanceDueStatusPort,
      facilityNaming: $this->createStub(FacilityNamingPort::class),
    );

    $result = $handler->__invoke(new GetEquipmentQuery(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
    ));

    self::assertInstanceOf(GetEquipmentResult::class, $result);
    self::assertSame(self::EQUIP_ID, $result->equipmentId);
    self::assertSame(self::ORG_ID, $result->organizationId);
    self::assertSame('fire_extinguisher', $result->type);
    self::assertSame('in_stock', $result->status);
    self::assertSame([], $result->tags);
    self::assertSame('due_soon', $result->maintenanceDueStatus);
    self::assertSame('due_soon', $result->controlDueStatus);
    self::assertSame('unscheduled', $result->serviceDueStatus);
    self::assertNull($result->controlNextDueAt);
    self::assertNull($result->serviceNextDueAt);
  }

  #[Test]
  public function projectsControlAndServiceIndependentlyThroughTheOwnerPort(): void
  {
    $equipment = Equipment::create(
      EquipmentId::fromString(self::EQUIP_ID),
      EquipmentOrganizationId::fromString(self::ORG_ID),
      EquipmentType::FIRE_EXTINGUISHER,
    );
    $repository = $this->createStub(EquipmentRepositoryPort::class);
    $repository->method('findById')->willReturn($equipment);
    $legacy = $this->createMock(MaintenanceDueStatusPort::class);
    $legacy->expects(self::never())->method('dueStatusesForEquipment');
    $operations = $this->createMock(MaintenanceOperationsDuePort::class);
    $operations->expects(self::once())->method('forEquipment')->with(self::ORG_ID, [self::EQUIP_ID])->willReturn([
      self::EQUIP_ID => new MaintenanceEquipmentOperationsDue(
        'up_to_date',
        'overdue',
        new DateTimeImmutable('2027-02-01T10:00:00+01:00'),
        new DateTimeImmutable('2026-10-01T08:30:00+00:00'),
        'plans',
      ),
    ]);
    $handler = new GetEquipmentHandler($repository, $this->createStub(TagRepositoryPort::class), $legacy, $this->createStub(FacilityNamingPort::class), $operations);
    $result = $handler(new GetEquipmentQuery(self::ORG_ID, self::EQUIP_ID));
    self::assertSame('up_to_date', $result->controlDueStatus);
    self::assertSame('up_to_date', $result->maintenanceDueStatus);
    self::assertSame('overdue', $result->serviceDueStatus);
    self::assertSame('2027-02-01T10:00:00+01:00', $result->controlNextDueAt);
    self::assertSame('2026-10-01T08:30:00+00:00', $result->serviceNextDueAt);
    self::assertSame('in_stock', $result->status);
  }

  #[Test]
  public function testInvokeDefaultsMaintenanceDueStatusToUnscheduledWhenPortOmitsTheId(): void
  {
    $equipment = Equipment::create(
      id: EquipmentId::fromString(self::EQUIP_ID),
      organizationId: EquipmentOrganizationId::fromString(self::ORG_ID),
      type: EquipmentType::FIRE_EXTINGUISHER,
    );

    /** @var EquipmentRepositoryPort&MockObject $equipmentRepository */
    $equipmentRepository = $this->createMock(EquipmentRepositoryPort::class);
    $equipmentRepository->expects(self::once())
      ->method('findById')
      ->willReturn($equipment);

    /** @var TagRepositoryPort&MockObject $tagRepository */
    $tagRepository = $this->createMock(TagRepositoryPort::class);
    $tagRepository->expects(self::once())
      ->method('findByEquipmentId')
      ->willReturn([]);

    /** @var MaintenanceDueStatusPort&MockObject $maintenanceDueStatusPort */
    $maintenanceDueStatusPort = $this->createMock(MaintenanceDueStatusPort::class);
    $maintenanceDueStatusPort->expects(self::once())
      ->method('dueStatusesForEquipment')
      ->willReturn([]);

    $handler = new GetEquipmentHandler(
      equipmentRepository: $equipmentRepository,
      tagRepository: $tagRepository,
      maintenanceDueStatusPort: $maintenanceDueStatusPort,
      facilityNaming: $this->createStub(FacilityNamingPort::class),
    );

    $result = $handler->__invoke(new GetEquipmentQuery(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
    ));

    self::assertSame('unscheduled', $result->maintenanceDueStatus);
  }
}
