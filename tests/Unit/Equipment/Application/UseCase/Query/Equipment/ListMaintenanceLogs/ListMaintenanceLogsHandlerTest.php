<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Application\UseCase\Query\Equipment\ListMaintenanceLogs;

use Equipment\Application\Port\Outbound\{EquipmentRepositoryPort, MaintenanceLogRepositoryPort};
use Equipment\Application\UseCase\Query\Equipment\ListMaintenanceLogs\{ListMaintenanceLogsHandler, ListMaintenanceLogsQuery, ListMaintenanceLogsResult};
use Equipment\Domain\Exception\EquipmentNotFoundException;
use Equipment\Domain\Model\Equipment\Equipment;
use Equipment\Domain\Model\MaintenanceLog\EquipmentMaintenanceLog;
use Equipment\Domain\ValueObject\{EquipmentId, EquipmentOrganizationId, EquipmentType, MaintenanceLogId};
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Pagination\Pagination;

#[CoversClass(ListMaintenanceLogsHandler::class)]
final class ListMaintenanceLogsHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655490001';

  private const string EQUIP_ID = '550e8400-e29b-41d4-a716-446655490002';

  private const string LOG_ID = '550e8400-e29b-41d4-a716-446655490010';

  #[Test]
  public function testInvokeThrowsInvalidArgumentOnInvalidEquipmentId(): void
  {
    $handler = new ListMaintenanceLogsHandler(
      equipmentRepository: $this->createStub(EquipmentRepositoryPort::class),
      maintenanceLogRepository: $this->createStub(MaintenanceLogRepositoryPort::class),
    );

    $this->expectException(InvalidArgumentException::class);

    $handler->__invoke(new ListMaintenanceLogsQuery(
      organizationId: self::ORG_ID,
      equipmentId: 'not-a-uuid',
      pagination: new Pagination(offset: 0, limit: 20),
    ));
  }

  #[Test]
  public function testInvokeThrowsInvalidArgumentOnInvalidOrganizationId(): void
  {
    $handler = new ListMaintenanceLogsHandler(
      equipmentRepository: $this->createStub(EquipmentRepositoryPort::class),
      maintenanceLogRepository: $this->createStub(MaintenanceLogRepositoryPort::class),
    );

    $this->expectException(InvalidArgumentException::class);

    $handler->__invoke(new ListMaintenanceLogsQuery(
      organizationId: 'bad-org',
      equipmentId: self::EQUIP_ID,
      pagination: new Pagination(offset: 0, limit: 20),
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

    $handler = new ListMaintenanceLogsHandler(
      equipmentRepository: $equipmentRepository,
      maintenanceLogRepository: $this->createStub(MaintenanceLogRepositoryPort::class),
    );

    $this->expectException(EquipmentNotFoundException::class);

    $handler->__invoke(new ListMaintenanceLogsQuery(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
      pagination: new Pagination(offset: 0, limit: 20),
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

    $equipmentRepository = $this->createStub(EquipmentRepositoryPort::class);
    $equipmentRepository->method('findById')->willReturn($equipment);

    $handler = new ListMaintenanceLogsHandler(
      equipmentRepository: $equipmentRepository,
      maintenanceLogRepository: $this->createStub(MaintenanceLogRepositoryPort::class),
    );

    $this->expectException(EquipmentNotFoundException::class);

    $handler->__invoke(new ListMaintenanceLogsQuery(
      organizationId: '550e8400-e29b-41d4-a716-446655499999',
      equipmentId: self::EQUIP_ID,
      pagination: new Pagination(offset: 0, limit: 20),
    ));
  }

  #[Test]
  public function testInvokeReturnsEmptyResultWhenNoLogs(): void
  {
    $equipment = Equipment::create(
      id: EquipmentId::fromString(self::EQUIP_ID),
      organizationId: EquipmentOrganizationId::fromString(self::ORG_ID),
      type: EquipmentType::FIRE_EXTINGUISHER,
    );

    $equipmentRepository = $this->createStub(EquipmentRepositoryPort::class);
    $equipmentRepository->method('findById')->willReturn($equipment);

    /** @var MaintenanceLogRepositoryPort&MockObject $maintenanceLogRepository */
    $maintenanceLogRepository = $this->createMock(MaintenanceLogRepositoryPort::class);
    $maintenanceLogRepository->expects(self::once())
      ->method('countByEquipmentId')
      ->willReturn(0);
    $maintenanceLogRepository->expects(self::once())
      ->method('findByEquipmentId')
      ->willReturn([]);

    $handler = new ListMaintenanceLogsHandler(
      equipmentRepository: $equipmentRepository,
      maintenanceLogRepository: $maintenanceLogRepository,
    );

    $result = $handler->__invoke(new ListMaintenanceLogsQuery(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
      pagination: new Pagination(offset: 0, limit: 20),
    ));

    self::assertInstanceOf(ListMaintenanceLogsResult::class, $result);
    self::assertSame([], $result->logs);
    self::assertSame(0, $result->total);
  }

  #[Test]
  public function testInvokeReturnsLogsMappedToArrays(): void
  {
    $equipment = Equipment::create(
      id: EquipmentId::fromString(self::EQUIP_ID),
      organizationId: EquipmentOrganizationId::fromString(self::ORG_ID),
      type: EquipmentType::FIRE_EXTINGUISHER,
    );

    $log = EquipmentMaintenanceLog::open(
      id: MaintenanceLogId::fromString(self::LOG_ID),
      equipmentId: EquipmentId::fromString(self::EQUIP_ID),
      organizationId: EquipmentOrganizationId::fromString(self::ORG_ID),
    );

    $equipmentRepository = $this->createStub(EquipmentRepositoryPort::class);
    $equipmentRepository->method('findById')->willReturn($equipment);

    $maintenanceLogRepository = $this->createStub(MaintenanceLogRepositoryPort::class);
    $maintenanceLogRepository->method('countByEquipmentId')->willReturn(1);
    $maintenanceLogRepository->method('findByEquipmentId')->willReturn([$log]);

    $handler = new ListMaintenanceLogsHandler(
      equipmentRepository: $equipmentRepository,
      maintenanceLogRepository: $maintenanceLogRepository,
    );

    $result = $handler->__invoke(new ListMaintenanceLogsQuery(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
      pagination: new Pagination(offset: 0, limit: 20),
    ));

    self::assertCount(1, $result->logs);
    self::assertSame(self::LOG_ID, $result->logs[0]['id']);
    self::assertSame(self::EQUIP_ID, $result->logs[0]['equipmentId']);
    self::assertSame(self::ORG_ID, $result->logs[0]['organizationId']);
    self::assertNull($result->logs[0]['completedAt']);
    self::assertNotEmpty($result->logs[0]['startedAt']);
    self::assertSame(1, $result->total);
  }
}
