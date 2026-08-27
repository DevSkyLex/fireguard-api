<?php

declare(strict_types=1);

namespace Tests\Unit\Maintenance\Application\UseCase\Query\ExportMaintenanceSchedules;

use Maintenance\Application\Contract\Export\MaintenanceScheduleExportCandidate;
use Maintenance\Application\Port\Outbound\Naming\{MaintenanceEquipmentNamingPort, MaintenanceFacilityNamingPort};
use Maintenance\Application\Port\Outbound\Schedule\MaintenanceScheduleRepositoryPort;
use Maintenance\Application\UseCase\Query\ExportMaintenanceSchedules\{ExportMaintenanceSchedulesHandler, ExportMaintenanceSchedulesQuery, ExportMaintenanceSchedulesResult};
use Maintenance\Domain\Exception\{MaintenanceAccessDeniedException, MaintenanceExportTooLargeException, MaintenanceNotFoundException};
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test ExportMaintenanceSchedulesHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ExportMaintenanceSchedulesHandler::class)]
final class ExportMaintenanceSchedulesHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655449301';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655449302';

  private const string EQUIPMENT_ID = '550e8400-e29b-41d4-a716-446655449303';

  private const string FACILITY_ID = '550e8400-e29b-41d4-a716-446655449304';

  #[Test]
  public function testInvokeThrowsAccessDeniedWithoutPermission(): void
  {
    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('resolveAccess')
      ->with(self::USER_ID, self::ORG_ID, 'organization.maintenance.read')
      ->willReturn(OrganizationAccessDecision::MISSING_PERMISSION);

    /** @var MaintenanceScheduleRepositoryPort&MockObject $schedules */
    $schedules = $this->createMock(MaintenanceScheduleRepositoryPort::class);
    $schedules->expects(self::never())->method('countForExport');
    $schedules->expects(self::never())->method('listExportCandidates');

    $handler = new ExportMaintenanceSchedulesHandler(
      schedules: $schedules,
      equipmentNaming: $this->createStub(MaintenanceEquipmentNamingPort::class),
      facilityNaming: $this->createStub(MaintenanceFacilityNamingPort::class),
      authorization: $authorization,
    );

    $this->expectException(MaintenanceAccessDeniedException::class);

    $handler->__invoke(new ExportMaintenanceSchedulesQuery(self::USER_ID, self::ORG_ID));
  }

  #[Test]
  public function testInvokeThrowsNotFoundWhenTheCallerIsOutsideTheOwningOrganization(): void
  {
    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('resolveAccess')
      ->with(self::USER_ID, self::ORG_ID, 'organization.maintenance.read')
      ->willReturn(OrganizationAccessDecision::OUTSIDE_SCOPE);

    /** @var MaintenanceScheduleRepositoryPort&MockObject $schedules */
    $schedules = $this->createMock(MaintenanceScheduleRepositoryPort::class);
    $schedules->expects(self::never())->method('countForExport');

    $handler = new ExportMaintenanceSchedulesHandler(
      schedules: $schedules,
      equipmentNaming: $this->createStub(MaintenanceEquipmentNamingPort::class),
      facilityNaming: $this->createStub(MaintenanceFacilityNamingPort::class),
      authorization: $authorization,
    );

    $this->expectException(MaintenanceNotFoundException::class);

    $handler->__invoke(new ExportMaintenanceSchedulesQuery(self::USER_ID, self::ORG_ID));
  }

  #[Test]
  public function testInvokeThrowsWhenMatchCountExceedsTheCap(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    /** @var MaintenanceScheduleRepositoryPort&MockObject $schedules */
    $schedules = $this->createMock(MaintenanceScheduleRepositoryPort::class);
    $schedules->expects(self::once())
      ->method('countForExport')
      ->with(self::ORG_ID, null, null, null)
      ->willReturn(ExportMaintenanceSchedulesHandler::MAX_EXPORT_ROWS + 1);
    $schedules->expects(self::never())->method('listExportCandidates');

    $handler = new ExportMaintenanceSchedulesHandler(
      schedules: $schedules,
      equipmentNaming: $this->createStub(MaintenanceEquipmentNamingPort::class),
      facilityNaming: $this->createStub(MaintenanceFacilityNamingPort::class),
      authorization: $authorization,
    );

    $this->expectException(MaintenanceExportTooLargeException::class);

    $handler->__invoke(new ExportMaintenanceSchedulesQuery(self::USER_ID, self::ORG_ID));
  }

  #[Test]
  public function testInvokeResolvesEquipmentAndFacilityNamesInBulkAndFallsBackToIdWhenUnresolved(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    $resolved = new MaintenanceScheduleExportCandidate(
      id: 'schedule-1',
      equipmentId: self::EQUIPMENT_ID,
      equipmentType: 'extinguisher',
      facilityId: self::FACILITY_ID,
      intervalOverride: 'P6M',
      lastInspectionClosedAt: '2026-07-01T00:00:00+00:00',
      nextDueAt: '2027-01-01T00:00:00+00:00',
      dueStatus: 'up_to_date',
      createdAt: '2026-01-01T00:00:00+00:00',
      updatedAt: '2026-07-01T00:00:00+00:00',
    );
    $unresolved = new MaintenanceScheduleExportCandidate(
      id: 'schedule-2',
      equipmentId: 'unknown-equipment',
      equipmentType: 'fire_alarm',
      facilityId: 'unknown-facility',
      intervalOverride: null,
      lastInspectionClosedAt: null,
      nextDueAt: null,
      dueStatus: 'unscheduled',
      createdAt: '2026-01-01T00:00:00+00:00',
      updatedAt: '2026-01-01T00:00:00+00:00',
    );

    /** @var MaintenanceScheduleRepositoryPort&MockObject $schedules */
    $schedules = $this->createMock(MaintenanceScheduleRepositoryPort::class);
    $schedules->expects(self::once())
      ->method('countForExport')
      ->with(self::ORG_ID, null, null, null)
      ->willReturn(2);
    $schedules->expects(self::once())
      ->method('listExportCandidates')
      ->with(self::ORG_ID, null, null, null)
      ->willReturn([$resolved, $unresolved]);

    /** @var MaintenanceEquipmentNamingPort&MockObject $equipmentNaming */
    $equipmentNaming = $this->createMock(MaintenanceEquipmentNamingPort::class);
    $equipmentNaming->expects(self::once())
      ->method('findSerialNumbersByIds')
      ->with([self::EQUIPMENT_ID, 'unknown-equipment'])
      ->willReturn([self::EQUIPMENT_ID => 'SN-001']);

    /** @var MaintenanceFacilityNamingPort&MockObject $facilityNaming */
    $facilityNaming = $this->createMock(MaintenanceFacilityNamingPort::class);
    $facilityNaming->expects(self::once())
      ->method('findNamesByIds')
      ->with([self::FACILITY_ID, 'unknown-facility'])
      ->willReturn([self::FACILITY_ID => 'Main Warehouse']);

    $handler = new ExportMaintenanceSchedulesHandler(
      schedules: $schedules,
      equipmentNaming: $equipmentNaming,
      facilityNaming: $facilityNaming,
      authorization: $authorization,
    );

    $result = $handler->__invoke(new ExportMaintenanceSchedulesQuery(self::USER_ID, self::ORG_ID));

    self::assertInstanceOf(ExportMaintenanceSchedulesResult::class, $result);
    self::assertSame(2, $result->total);
    self::assertCount(2, $result->rows);
    self::assertSame(self::EQUIPMENT_ID, $result->rows[0]->equipmentId);
    self::assertSame('SN-001', $result->rows[0]->equipmentSerial);
    self::assertSame(self::FACILITY_ID, $result->rows[0]->facilityId);
    self::assertSame('Main Warehouse', $result->rows[0]->facilityName);
    self::assertSame('unknown-equipment', $result->rows[1]->equipmentId);
    self::assertNull($result->rows[1]->equipmentSerial, 'An unresolvable equipment id must resolve to a null serial, not an empty string.');
    self::assertSame('unknown-facility', $result->rows[1]->facilityId);
    self::assertNull($result->rows[1]->facilityName);
  }

  #[Test]
  public function testInvokePassesTheCheapFiltersThrough(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    /** @var MaintenanceScheduleRepositoryPort&MockObject $schedules */
    $schedules = $this->createMock(MaintenanceScheduleRepositoryPort::class);
    $schedules->expects(self::once())
      ->method('countForExport')
      ->with(self::ORG_ID, self::FACILITY_ID, 'extinguisher', 'overdue')
      ->willReturn(0);
    $schedules->expects(self::once())
      ->method('listExportCandidates')
      ->with(self::ORG_ID, self::FACILITY_ID, 'extinguisher', 'overdue')
      ->willReturn([]);

    $equipmentNaming = $this->createStub(MaintenanceEquipmentNamingPort::class);
    $equipmentNaming->method('findSerialNumbersByIds')->willReturn([]);

    $facilityNaming = $this->createStub(MaintenanceFacilityNamingPort::class);
    $facilityNaming->method('findNamesByIds')->willReturn([]);

    $handler = new ExportMaintenanceSchedulesHandler(
      schedules: $schedules,
      equipmentNaming: $equipmentNaming,
      facilityNaming: $facilityNaming,
      authorization: $authorization,
    );

    $result = $handler->__invoke(new ExportMaintenanceSchedulesQuery(
      self::USER_ID,
      self::ORG_ID,
      facilityId: self::FACILITY_ID,
      equipmentType: 'extinguisher',
      dueStatus: 'overdue',
    ));

    self::assertSame(0, $result->total);
    self::assertSame([], $result->rows);
  }
}
