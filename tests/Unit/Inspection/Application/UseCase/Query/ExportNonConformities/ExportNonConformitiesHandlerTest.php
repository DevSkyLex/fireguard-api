<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Application\UseCase\Query\ExportNonConformities;

use DateTimeImmutable;
use Inspection\Application\Contract\Export\NonConformityExportCandidate;
use Inspection\Application\Port\Outbound\{EquipmentNamingPort, FacilityNamingPort, NonConformityRepositoryPort};
use Inspection\Application\UseCase\Query\ExportNonConformities\{ExportNonConformitiesHandler, ExportNonConformitiesQuery, ExportNonConformitiesResult};
use Inspection\Domain\Exception\{InspectionAccessDeniedException, InspectionExportTooLargeException, InspectionNotFoundException};
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\ClockPort;

/**
 * Test ExportNonConformitiesHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ExportNonConformitiesHandler::class)]
final class ExportNonConformitiesHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655449401';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655449402';

  private const string FACILITY_ID = '550e8400-e29b-41d4-a716-446655449403';

  private const string EQUIPMENT_ID = '550e8400-e29b-41d4-a716-446655449404';

  #[Test]
  public function testInvokeThrowsAccessDeniedWithoutPermission(): void
  {
    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('resolveAccess')
      ->with(self::USER_ID, self::ORG_ID, 'organization.inspection.read')
      ->willReturn(OrganizationAccessDecision::MISSING_PERMISSION);

    /** @var NonConformityRepositoryPort&MockObject $nonConformityRepository */
    $nonConformityRepository = $this->createMock(NonConformityRepositoryPort::class);
    $nonConformityRepository->expects(self::never())->method('countExportCandidates');
    $nonConformityRepository->expects(self::never())->method('listExportCandidates');

    $handler = new ExportNonConformitiesHandler(
      nonConformityRepository: $nonConformityRepository,
      facilityNaming: $this->createStub(FacilityNamingPort::class),
      equipmentNaming: $this->createStub(EquipmentNamingPort::class),
      authorization: $authorization,
      clock: $this->createStub(ClockPort::class),
    );

    $this->expectException(InspectionAccessDeniedException::class);

    $handler->__invoke(new ExportNonConformitiesQuery(self::USER_ID, self::ORG_ID, []));
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

    /** @var NonConformityRepositoryPort&MockObject $nonConformityRepository */
    $nonConformityRepository = $this->createMock(NonConformityRepositoryPort::class);
    $nonConformityRepository->expects(self::never())->method('countExportCandidates');

    $handler = new ExportNonConformitiesHandler(
      nonConformityRepository: $nonConformityRepository,
      facilityNaming: $this->createStub(FacilityNamingPort::class),
      equipmentNaming: $this->createStub(EquipmentNamingPort::class),
      authorization: $authorization,
      clock: $this->createStub(ClockPort::class),
    );

    $this->expectException(InspectionNotFoundException::class);

    $handler->__invoke(new ExportNonConformitiesQuery(self::USER_ID, self::ORG_ID, []));
  }

  #[Test]
  public function testInvokeThrowsWhenMatchCountExceedsTheCap(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    /** @var NonConformityRepositoryPort&MockObject $nonConformityRepository */
    $nonConformityRepository = $this->createMock(NonConformityRepositoryPort::class);
    $nonConformityRepository->expects(self::once())
      ->method('countExportCandidates')
      ->willReturn(ExportNonConformitiesHandler::MAX_EXPORT_ROWS + 1);
    $nonConformityRepository->expects(self::never())->method('listExportCandidates');

    $handler = new ExportNonConformitiesHandler(
      nonConformityRepository: $nonConformityRepository,
      facilityNaming: $this->createStub(FacilityNamingPort::class),
      equipmentNaming: $this->createStub(EquipmentNamingPort::class),
      authorization: $authorization,
      clock: $this->createStub(ClockPort::class),
    );

    $this->expectException(InspectionExportTooLargeException::class);

    $handler->__invoke(new ExportNonConformitiesQuery(self::USER_ID, self::ORG_ID, []));
  }

  #[Test]
  public function testInvokeResolvesNamesInBulkAndComputesAgeAgainstTheClock(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    $clock = $this->createStub(ClockPort::class);
    $clock->method('now')->willReturn(new DateTimeImmutable('2026-08-20T00:00:00+00:00'));

    $resolved = new NonConformityExportCandidate(
      id: 'nc-1',
      inspectionId: 'inspection-1',
      severity: 'critical',
      status: 'open',
      facilityId: self::FACILITY_ID,
      equipmentId: self::EQUIPMENT_ID,
      createdAt: '2026-08-10T00:00:00+00:00',
      resolvedAt: null,
    );
    $unresolved = new NonConformityExportCandidate(
      id: 'nc-2',
      inspectionId: 'inspection-2',
      severity: 'low',
      status: 'done',
      facilityId: 'unknown-facility',
      equipmentId: 'unknown-equipment',
      createdAt: '2026-08-15T00:00:00+00:00',
      resolvedAt: '2026-08-18T00:00:00+00:00',
    );

    /** @var NonConformityRepositoryPort&MockObject $nonConformityRepository */
    $nonConformityRepository = $this->createMock(NonConformityRepositoryPort::class);
    $nonConformityRepository->expects(self::once())
      ->method('countExportCandidates')
      ->willReturn(2);
    $nonConformityRepository->expects(self::once())
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

    $handler = new ExportNonConformitiesHandler(
      nonConformityRepository: $nonConformityRepository,
      facilityNaming: $facilityNaming,
      equipmentNaming: $equipmentNaming,
      authorization: $authorization,
      clock: $clock,
    );

    $result = $handler->__invoke(new ExportNonConformitiesQuery(self::USER_ID, self::ORG_ID, []));

    self::assertInstanceOf(ExportNonConformitiesResult::class, $result);
    self::assertSame(2, $result->total);
    self::assertCount(2, $result->rows);

    self::assertSame('Main Warehouse', $result->rows[0]->facilityName);
    self::assertSame('SN-001', $result->rows[0]->equipmentSerialNumber);
    self::assertSame(10, $result->rows[0]->ageInDays, 'createdAt 2026-08-10 against a clock of 2026-08-20 is 10 whole days.');

    self::assertNull($result->rows[1]->facilityName, 'An unresolvable facility id must resolve to a null name, not an empty string.');
    self::assertNull($result->rows[1]->equipmentSerialNumber);
    self::assertSame(5, $result->rows[1]->ageInDays);
  }
}
