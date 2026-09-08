<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Application\UseCase\Query\ExportInspections;

use Inspection\Application\Contract\Export\InspectionExportCandidate;
use Inspection\Application\Port\Outbound\{ChecklistRepositoryPort, EquipmentNamingPort, FacilityNamingPort, InspectionRepositoryPort, NonConformityRepositoryPort};
use Inspection\Application\UseCase\Query\ExportInspections\{ExportInspectionsHandler, ExportInspectionsQuery, ExportInspectionsResult};
use Inspection\Domain\Exception\{InspectionAccessDeniedException, InspectionExportTooLargeException, InspectionNotFoundException};
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test ExportInspectionsHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ExportInspectionsHandler::class)]
final class ExportInspectionsHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655449301';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655449302';

  private const string FACILITY_ID = '550e8400-e29b-41d4-a716-446655449303';

  private const string EQUIPMENT_ID = '550e8400-e29b-41d4-a716-446655449304';

  private const string CHECKLIST_ID = '550e8400-e29b-41d4-a716-446655449305';

  #[Test]
  public function testInvokeThrowsAccessDeniedWithoutPermission(): void
  {
    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('resolveAccess')
      ->with(self::USER_ID, self::ORG_ID, 'organization.inspection.read')
      ->willReturn(OrganizationAccessDecision::MISSING_PERMISSION);

    /** @var InspectionRepositoryPort&MockObject $inspectionRepository */
    $inspectionRepository = $this->createMock(InspectionRepositoryPort::class);
    $inspectionRepository->expects(self::never())->method('countExportCandidates');
    $inspectionRepository->expects(self::never())->method('listExportCandidates');

    $handler = new ExportInspectionsHandler(
      inspectionRepository: $inspectionRepository,
      nonConformityRepository: $this->createStub(NonConformityRepositoryPort::class),
      facilityNaming: $this->createStub(FacilityNamingPort::class),
      equipmentNaming: $this->createStub(EquipmentNamingPort::class),
      checklistRepository: $this->createStub(ChecklistRepositoryPort::class),
      authorization: $authorization,
    );

    $this->expectException(InspectionAccessDeniedException::class);

    $handler->__invoke(new ExportInspectionsQuery(self::USER_ID, self::ORG_ID, []));
  }

  #[Test]
  public function testInvokeThrowsNotFoundWhenTheCallerIsOutsideTheOwningOrganization(): void
  {
    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('resolveAccess')
      ->with(self::USER_ID, self::ORG_ID, 'organization.inspection.read')
      ->willReturn(OrganizationAccessDecision::OUTSIDE_SCOPE);

    /** @var InspectionRepositoryPort&MockObject $inspectionRepository */
    $inspectionRepository = $this->createMock(InspectionRepositoryPort::class);
    $inspectionRepository->expects(self::never())->method('countExportCandidates');

    $handler = new ExportInspectionsHandler(
      inspectionRepository: $inspectionRepository,
      nonConformityRepository: $this->createStub(NonConformityRepositoryPort::class),
      facilityNaming: $this->createStub(FacilityNamingPort::class),
      equipmentNaming: $this->createStub(EquipmentNamingPort::class),
      checklistRepository: $this->createStub(ChecklistRepositoryPort::class),
      authorization: $authorization,
    );

    $this->expectException(InspectionNotFoundException::class);

    $handler->__invoke(new ExportInspectionsQuery(self::USER_ID, self::ORG_ID, []));
  }

  #[Test]
  public function testInvokeThrowsWhenMatchCountExceedsTheCap(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    /** @var InspectionRepositoryPort&MockObject $inspectionRepository */
    $inspectionRepository = $this->createMock(InspectionRepositoryPort::class);
    $inspectionRepository->expects(self::once())
      ->method('countExportCandidates')
      ->willReturn(ExportInspectionsHandler::MAX_EXPORT_ROWS + 1);
    $inspectionRepository->expects(self::never())->method('listExportCandidates');

    $handler = new ExportInspectionsHandler(
      inspectionRepository: $inspectionRepository,
      nonConformityRepository: $this->createStub(NonConformityRepositoryPort::class),
      facilityNaming: $this->createStub(FacilityNamingPort::class),
      equipmentNaming: $this->createStub(EquipmentNamingPort::class),
      checklistRepository: $this->createStub(ChecklistRepositoryPort::class),
      authorization: $authorization,
    );

    $this->expectException(InspectionExportTooLargeException::class);

    $handler->__invoke(new ExportInspectionsQuery(self::USER_ID, self::ORG_ID, []));
  }

  #[Test]
  public function testInvokeResolvesNamesAndNonConformityCountsInBulk(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    $resolved = new InspectionExportCandidate(
      id: 'inspection-1',
      equipmentId: self::EQUIPMENT_ID,
      facilityId: self::FACILITY_ID,
      checklistId: self::CHECKLIST_ID,
      status: 'closed',
      result: 'pass',
      performedAt: '2026-08-01T00:00:00+00:00',
      createdAt: '2026-08-01T00:00:00+00:00',
      updatedAt: '2026-08-02T00:00:00+00:00',
    );
    $unresolved = new InspectionExportCandidate(
      id: 'inspection-2',
      equipmentId: 'unknown-equipment',
      facilityId: 'unknown-facility',
      checklistId: 'unknown-checklist',
      status: 'draft',
      result: 'fail',
      performedAt: '2026-08-03T00:00:00+00:00',
      createdAt: '2026-08-03T00:00:00+00:00',
      updatedAt: '2026-08-03T00:00:00+00:00',
    );

    /** @var InspectionRepositoryPort&MockObject $inspectionRepository */
    $inspectionRepository = $this->createMock(InspectionRepositoryPort::class);
    $inspectionRepository->expects(self::once())
      ->method('countExportCandidates')
      ->willReturn(2);
    $inspectionRepository->expects(self::once())
      ->method('listExportCandidates')
      ->willReturn([$resolved, $unresolved]);

    /** @var FacilityNamingPort&MockObject $facilityNaming */
    $facilityNaming = $this->createMock(FacilityNamingPort::class);
    $facilityNaming->expects(self::once())
      ->method('findNamesByIds')
      ->with([self::FACILITY_ID, 'unknown-facility'])
      ->willReturn([self::FACILITY_ID => 'Main Warehouse']);

    /** @var EquipmentNamingPort&MockObject $equipmentNaming */
    $equipmentNaming = $this->createMock(EquipmentNamingPort::class);
    $equipmentNaming->expects(self::once())
      ->method('findSerialNumbersByIds')
      ->with([self::EQUIPMENT_ID, 'unknown-equipment'])
      ->willReturn([self::EQUIPMENT_ID => 'SN-001']);

    /** @var ChecklistRepositoryPort&MockObject $checklistRepository */
    $checklistRepository = $this->createMock(ChecklistRepositoryPort::class);
    $checklistRepository->expects(self::once())
      ->method('findNamesByIds')
      ->with([self::CHECKLIST_ID, 'unknown-checklist'])
      ->willReturn([self::CHECKLIST_ID => 'Extinguisher Quarterly']);

    /** @var NonConformityRepositoryPort&MockObject $nonConformityRepository */
    $nonConformityRepository = $this->createMock(NonConformityRepositoryPort::class);
    $nonConformityRepository->expects(self::once())
      ->method('countsOpenByInspectionIds')
      ->with(['inspection-1', 'inspection-2'])
      ->willReturn(['inspection-1' => 1]);
    $nonConformityRepository->expects(self::once())
      ->method('countsByInspectionIds')
      ->with(['inspection-1', 'inspection-2'])
      ->willReturn(['inspection-1' => 3]);

    $handler = new ExportInspectionsHandler(
      inspectionRepository: $inspectionRepository,
      nonConformityRepository: $nonConformityRepository,
      facilityNaming: $facilityNaming,
      equipmentNaming: $equipmentNaming,
      checklistRepository: $checklistRepository,
      authorization: $authorization,
    );

    $result = $handler->__invoke(new ExportInspectionsQuery(self::USER_ID, self::ORG_ID, []));

    self::assertInstanceOf(ExportInspectionsResult::class, $result);
    self::assertSame(2, $result->total);
    self::assertCount(2, $result->rows);

    self::assertSame('Main Warehouse', $result->rows[0]->facilityName);
    self::assertSame('SN-001', $result->rows[0]->equipmentSerialNumber);
    self::assertSame('Extinguisher Quarterly', $result->rows[0]->checklistName);
    self::assertSame(1, $result->rows[0]->nonConformitiesOpen);
    self::assertSame(3, $result->rows[0]->nonConformitiesTotal);

    self::assertNull($result->rows[1]->facilityName, 'An unresolvable facility id must resolve to a null name, not an empty string.');
    self::assertNull($result->rows[1]->equipmentSerialNumber);
    self::assertNull($result->rows[1]->checklistName);
    self::assertSame(0, $result->rows[1]->nonConformitiesOpen, 'A row absent from the bulk count map must default to zero, not be dropped.');
    self::assertSame(0, $result->rows[1]->nonConformitiesTotal);
  }
}
