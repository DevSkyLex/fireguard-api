<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Application\UseCase\Query\Equipment\GetEquipmentKpis;

use Equipment\Application\Port\Outbound\{EquipmentRepositoryPort, MaintenanceDueStatusPort, NonConformityStatisticsPort};
use Equipment\Application\UseCase\Query\Equipment\GetEquipmentKpis\{GetEquipmentKpisHandler, GetEquipmentKpisQuery, GetEquipmentKpisResult};
use Equipment\Domain\Model\Equipment\Equipment;
use Equipment\Domain\ValueObject\{EquipmentId, EquipmentOrganizationId, EquipmentType};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

#[CoversClass(GetEquipmentKpisHandler::class)]
final class GetEquipmentKpisHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655449001';

  private const string EQUIP_ID_1 = '550e8400-e29b-41d4-a716-446655449002';

  private const string EQUIP_ID_2 = '550e8400-e29b-41d4-a716-446655449003';

  private const string EQUIP_ID_3 = '550e8400-e29b-41d4-a716-446655449004';

  #[Test]
  public function testInvokeThrowsInvalidArgumentOnInvalidOrganizationId(): void
  {
    /** @var EquipmentRepositoryPort&MockObject $equipmentRepository */
    $equipmentRepository = $this->createMock(EquipmentRepositoryPort::class);
    $equipmentRepository->expects(self::never())->method('countOverviewByOrganizationId');

    $handler = new GetEquipmentKpisHandler(
      equipmentRepository: $equipmentRepository,
      maintenanceDueStatusPort: $this->createStub(MaintenanceDueStatusPort::class),
      nonConformityStatistics: $this->createStub(NonConformityStatisticsPort::class),
    );

    $this->expectException(InvalidValueException::class);

    $handler->__invoke(new GetEquipmentKpisQuery(organizationId: 'not-a-uuid'));
  }

  #[Test]
  public function testInvokeTalliesCompliantAndDueSoonFromTheBatchDueStatusMap(): void
  {
    $equipment1 = Equipment::create(
      id: EquipmentId::fromString(self::EQUIP_ID_1),
      organizationId: EquipmentOrganizationId::fromString(self::ORG_ID),
      type: EquipmentType::FIRE_EXTINGUISHER,
    );
    $equipment2 = Equipment::create(
      id: EquipmentId::fromString(self::EQUIP_ID_2),
      organizationId: EquipmentOrganizationId::fromString(self::ORG_ID),
      type: EquipmentType::SMOKE_DETECTOR,
    );
    $equipment3 = Equipment::create(
      id: EquipmentId::fromString(self::EQUIP_ID_3),
      organizationId: EquipmentOrganizationId::fromString(self::ORG_ID),
      type: EquipmentType::SPRINKLER,
    );

    /** @var EquipmentRepositoryPort&MockObject $equipmentRepository */
    $equipmentRepository = $this->createMock(EquipmentRepositoryPort::class);
    $equipmentRepository->expects(self::once())
      ->method('countOverviewByOrganizationId')
      ->willReturn(['total' => 5, 'in_stock' => 1, 'operational' => 3, 'under_maintenance' => 1, 'decommissioned' => 0]);
    $equipmentRepository->expects(self::once())
      ->method('findByOrganizationId')
      ->willReturn([$equipment1, $equipment2, $equipment3]);

    /** @var MaintenanceDueStatusPort&MockObject $maintenanceDueStatusPort */
    $maintenanceDueStatusPort = $this->createMock(MaintenanceDueStatusPort::class);
    $maintenanceDueStatusPort->expects(self::once())
      ->method('dueStatusesForEquipment')
      ->with(self::ORG_ID, [self::EQUIP_ID_1, self::EQUIP_ID_2, self::EQUIP_ID_3])
      ->willReturn([
        self::EQUIP_ID_1 => 'up_to_date',
        self::EQUIP_ID_2 => 'due_soon',
        self::EQUIP_ID_3 => 'overdue',
      ]);

    /** @var NonConformityStatisticsPort&MockObject $nonConformityStatistics */
    $nonConformityStatistics = $this->createMock(NonConformityStatisticsPort::class);
    $nonConformityStatistics->expects(self::once())
      ->method('countOpenNonConformities')
      ->with(self::ORG_ID)
      ->willReturn(7);

    $handler = new GetEquipmentKpisHandler(
      equipmentRepository: $equipmentRepository,
      maintenanceDueStatusPort: $maintenanceDueStatusPort,
      nonConformityStatistics: $nonConformityStatistics,
    );

    $result = $handler->__invoke(new GetEquipmentKpisQuery(organizationId: self::ORG_ID));

    self::assertInstanceOf(GetEquipmentKpisResult::class, $result);
    self::assertSame(5, $result->totalAssets);
    self::assertSame(1, $result->compliant);
    self::assertSame(1, $result->dueSoon);
    self::assertSame(7, $result->openNonConformities);
  }

  #[Test]
  public function testInvokeReturnsZeroCompliantAndDueSoonWhenNoEquipment(): void
  {
    /** @var EquipmentRepositoryPort&MockObject $equipmentRepository */
    $equipmentRepository = $this->createMock(EquipmentRepositoryPort::class);
    $equipmentRepository->expects(self::once())
      ->method('countOverviewByOrganizationId')
      ->willReturn(['total' => 0, 'in_stock' => 0, 'operational' => 0, 'under_maintenance' => 0, 'decommissioned' => 0]);
    $equipmentRepository->expects(self::once())
      ->method('findByOrganizationId')
      ->willReturn([]);

    /** @var MaintenanceDueStatusPort&MockObject $maintenanceDueStatusPort */
    $maintenanceDueStatusPort = $this->createMock(MaintenanceDueStatusPort::class);
    $maintenanceDueStatusPort->expects(self::once())
      ->method('dueStatusesForEquipment')
      ->with(self::ORG_ID, [])
      ->willReturn([]);

    /** @var NonConformityStatisticsPort&MockObject $nonConformityStatistics */
    $nonConformityStatistics = $this->createMock(NonConformityStatisticsPort::class);
    $nonConformityStatistics->expects(self::once())
      ->method('countOpenNonConformities')
      ->willReturn(0);

    $handler = new GetEquipmentKpisHandler(
      equipmentRepository: $equipmentRepository,
      maintenanceDueStatusPort: $maintenanceDueStatusPort,
      nonConformityStatistics: $nonConformityStatistics,
    );

    $result = $handler->__invoke(new GetEquipmentKpisQuery(organizationId: self::ORG_ID));

    self::assertSame(0, $result->totalAssets);
    self::assertSame(0, $result->compliant);
    self::assertSame(0, $result->dueSoon);
    self::assertSame(0, $result->openNonConformities);
  }
}
