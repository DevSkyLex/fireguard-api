<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Application\UseCase\Query\ExportEquipments;

use Equipment\Application\Contract\Export\EquipmentExportCandidate;
use Equipment\Application\Port\Outbound\{EquipmentRepositoryPort, FacilityNamingPort};
use Equipment\Application\UseCase\Query\ExportEquipments\{ExportEquipmentsHandler, ExportEquipmentsQuery, ExportEquipmentsResult};
use Equipment\Domain\Exception\{EquipmentAccessDeniedException, EquipmentExportTooLargeException, EquipmentNotFoundException};
use Equipment\Domain\ValueObject\EquipmentOrganizationId;
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test ExportEquipmentsHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ExportEquipmentsHandler::class)]
final class ExportEquipmentsHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655450201';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655450202';

  private const string FACILITY_ID = '550e8400-e29b-41d4-a716-446655450203';

  #[Test]
  public function testInvokeThrowsAccessDeniedWithoutPermission(): void
  {
    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('resolveAccess')
      ->with(self::USER_ID, self::ORG_ID, 'organization.equipment.read')
      ->willReturn(OrganizationAccessDecision::MISSING_PERMISSION);

    /** @var EquipmentRepositoryPort&MockObject $repository */
    $repository = $this->createMock(EquipmentRepositoryPort::class);
    $repository->expects(self::never())->method('countEquipments');
    $repository->expects(self::never())->method('listEquipmentExportCandidates');

    $handler = new ExportEquipmentsHandler(
      repository: $repository,
      facilityNaming: $this->createStub(FacilityNamingPort::class),
      authorization: $authorization,
    );

    $this->expectException(EquipmentAccessDeniedException::class);

    $handler->__invoke(new ExportEquipmentsQuery(self::USER_ID, self::ORG_ID));
  }

  #[Test]
  public function testInvokeThrowsNotFoundWhenTheCallerIsOutsideTheOwningOrganization(): void
  {
    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('resolveAccess')
      ->with(self::USER_ID, self::ORG_ID, 'organization.equipment.read')
      ->willReturn(OrganizationAccessDecision::OUTSIDE_SCOPE);

    /** @var EquipmentRepositoryPort&MockObject $repository */
    $repository = $this->createMock(EquipmentRepositoryPort::class);
    $repository->expects(self::never())->method('countEquipments');

    $handler = new ExportEquipmentsHandler(
      repository: $repository,
      facilityNaming: $this->createStub(FacilityNamingPort::class),
      authorization: $authorization,
    );

    $this->expectException(EquipmentNotFoundException::class);

    $handler->__invoke(new ExportEquipmentsQuery(self::USER_ID, self::ORG_ID));
  }

  #[Test]
  public function testInvokeThrowsWhenMatchCountExceedsTheCap(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    /** @var EquipmentRepositoryPort&MockObject $repository */
    $repository = $this->createMock(EquipmentRepositoryPort::class);
    $repository->expects(self::once())
      ->method('countEquipments')
      ->with(self::callback(static fn (EquipmentOrganizationId $id): bool => ExportEquipmentsHandlerTest::ORG_ID === (string) $id))
      ->willReturn(ExportEquipmentsHandler::MAX_EXPORT_ROWS + 1);
    $repository->expects(self::never())->method('listEquipmentExportCandidates');

    $handler = new ExportEquipmentsHandler(
      repository: $repository,
      facilityNaming: $this->createStub(FacilityNamingPort::class),
      authorization: $authorization,
    );

    $this->expectException(EquipmentExportTooLargeException::class);

    $handler->__invoke(new ExportEquipmentsQuery(self::USER_ID, self::ORG_ID));
  }

  #[Test]
  public function testInvokeResolvesFacilityNamesInBulkAndFallsBackToIdWhenUnresolved(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    $resolved = new EquipmentExportCandidate(
      id: 'equipment-1',
      type: 'fire_extinguisher',
      subType: 'CO2',
      brand: 'Acme',
      model: 'X100',
      serialNumber: 'SN-1',
      locationLabel: 'Hallway',
      status: 'operational',
      facilityId: self::FACILITY_ID,
      installedAt: '2026-01-01T00:00:00+00:00',
      commissionedAt: '2026-01-02T00:00:00+00:00',
      createdAt: '2026-01-01T00:00:00+00:00',
      updatedAt: '2026-01-02T00:00:00+00:00',
    );
    $unresolved = new EquipmentExportCandidate(
      id: 'equipment-2',
      type: 'smoke_detector',
      subType: null,
      brand: null,
      model: null,
      serialNumber: null,
      locationLabel: null,
      status: 'in_stock',
      facilityId: 'unknown-facility',
      installedAt: null,
      commissionedAt: null,
      createdAt: '2026-01-01T00:00:00+00:00',
      updatedAt: '2026-01-02T00:00:00+00:00',
    );

    /** @var EquipmentRepositoryPort&MockObject $repository */
    $repository = $this->createMock(EquipmentRepositoryPort::class);
    $repository->expects(self::once())->method('countEquipments')->willReturn(2);
    $repository->expects(self::once())
      ->method('listEquipmentExportCandidates')
      ->willReturn([$resolved, $unresolved]);

    /** @var FacilityNamingPort&MockObject $facilityNaming */
    $facilityNaming = $this->createMock(FacilityNamingPort::class);
    $facilityNaming->expects(self::once())
      ->method('findNamesByIds')
      ->with([self::FACILITY_ID, 'unknown-facility'])
      ->willReturn([self::FACILITY_ID => 'Main Warehouse']);
    $facilityNaming->expects(self::once())
      ->method('findCodesByIds')
      ->with([self::FACILITY_ID, 'unknown-facility'])
      ->willReturn([self::FACILITY_ID => 'WH-01']);

    $handler = new ExportEquipmentsHandler(
      repository: $repository,
      facilityNaming: $facilityNaming,
      authorization: $authorization,
    );

    $result = $handler->__invoke(new ExportEquipmentsQuery(self::USER_ID, self::ORG_ID));

    self::assertInstanceOf(ExportEquipmentsResult::class, $result);
    self::assertSame(2, $result->total);
    self::assertCount(2, $result->rows);
    self::assertSame(self::FACILITY_ID, $result->rows[0]->facilityId);
    self::assertSame('Main Warehouse', $result->rows[0]->facilityName);
    self::assertSame('WH-01', $result->rows[0]->facilityCode);
    self::assertSame('unknown-facility', $result->rows[1]->facilityId);
    self::assertNull($result->rows[1]->facilityName, 'An unresolvable facility id must resolve to a null name, not an empty string.');
    self::assertNull($result->rows[1]->facilityCode, 'An unresolvable facility id must resolve to a null code, not an empty string.');
  }
}
