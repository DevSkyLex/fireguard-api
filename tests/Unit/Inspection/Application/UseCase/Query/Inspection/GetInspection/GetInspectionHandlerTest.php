<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Application\UseCase\Query\Inspection\GetInspection;

use DateTimeImmutable;
use Inspection\Application\Port\Outbound\{EquipmentNamingPort, FacilityNamingPort};
use Inspection\Application\Port\Outbound\Internal\{ChecklistRepositoryPort, InspectionRepositoryPort, NonConformityRepositoryPort};
use Inspection\Application\UseCase\Query\Inspection\GetInspection\{
  GetInspectionHandler,
  GetInspectionQuery
};
use Inspection\Domain\Exception\InspectionNotFoundException;
use Inspection\Domain\Model\Inspection\{Inspection, InspectionFinding, InspectionReferences};
use Inspection\Domain\ValueObject\{
  InspectionChecklistId,
  InspectionEquipmentId,
  InspectionFacilityId,
  InspectionId,
  InspectionOrganizationId,
  InspectionResult,
  InspectionStatus,
  Inspector,
  InspectorType
};
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Domain\Exception\InvalidValueException;
use User\Application\Contract\User\UserView;
use User\Application\Port\Inbound\UserDirectoryPort;

/**
 * Test GetInspectionHandlerTest.
 *
 * @category Test
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class GetInspectionHandlerTest extends TestCase
{
  private const string INSPECTION_ID = '11111111-1111-4111-8111-111111111111';

  private const string ORGANIZATION_ID = '22222222-2222-4222-8222-222222222222';

  private const string EQUIPMENT_ID = '33333333-3333-4333-8333-333333333333';

  private const string FACILITY_ID = '44444444-4444-4444-8444-444444444444';

  private const string CHECKLIST_ID = '55555555-5555-4555-8555-555555555555';

  private const string OTHER_ORGANIZATION_ID = '99999999-9999-4999-8999-999999999999';

  #[Test]
  public function itReturnsTheInspectionWithResolvedAssociationNames(): void
  {
    $performedAt = new DateTimeImmutable('2026-01-15T10:00:00+00:00');
    $createdAt = new DateTimeImmutable('2026-01-10T08:00:00+00:00');
    $updatedAt = new DateTimeImmutable('2026-01-12T09:30:00+00:00');

    $inspection = Inspection::reconstitute(
      id: InspectionId::fromString(self::INSPECTION_ID),
      organizationId: InspectionOrganizationId::fromString(self::ORGANIZATION_ID),
      references: new InspectionReferences(
        equipmentId: InspectionEquipmentId::fromString(self::EQUIPMENT_ID),
        inspector: Inspector::reconstitute(InspectorType::USER, 'Jane Doe', 'user-123', null),
        facilityId: InspectionFacilityId::fromString(self::FACILITY_ID),
        checklistId: InspectionChecklistId::fromString(self::CHECKLIST_ID),
      ),
      finding: new InspectionFinding(
        result: InspectionResult::PASS,
        status: InspectionStatus::SUBMITTED,
        performedAt: $performedAt,
        notes: 'All good',
        signature: 'signature-blob',
      ),
      createdAt: $createdAt,
      updatedAt: $updatedAt,
    );

    $inspectionRepository = $this->createStub(InspectionRepositoryPort::class);
    $inspectionRepository->method('findById')->willReturn($inspection);

    $nonConformityRepository = $this->createStub(NonConformityRepositoryPort::class);
    $nonConformityRepository->method('countByInspectionId')->willReturn(3);

    $equipmentNaming = $this->createStub(EquipmentNamingPort::class);
    $equipmentNaming->method('findSerialNumbersByIds')->willReturn([self::EQUIPMENT_ID => 'SN-001']);

    $facilityNaming = $this->createStub(FacilityNamingPort::class);
    $facilityNaming->method('findNamesByIds')->willReturn([self::FACILITY_ID => 'Main Warehouse']);

    $checklistRepository = $this->createStub(ChecklistRepositoryPort::class);
    $checklistRepository->method('findNamesByIds')->willReturn([self::CHECKLIST_ID => 'Monthly Checklist']);

    $profile = new UserView('user-123', 'inspector', 'inspector@example.com', 'Jane', 'Doe', 'avatar.png', 'active', true, null, $createdAt, null, true);
    $users = $this->createMock(UserDirectoryPort::class);
    $users->expects(self::once())->method('findById')->with('user-123')->willReturn($profile);

    $handler = new GetInspectionHandler(
      $inspectionRepository,
      $nonConformityRepository,
      $equipmentNaming,
      $facilityNaming,
      $checklistRepository,
      users: $users,
    );

    $result = $handler(new GetInspectionQuery(self::ORGANIZATION_ID, self::INSPECTION_ID));

    self::assertSame(self::INSPECTION_ID, $result->inspectionId);
    self::assertSame(self::ORGANIZATION_ID, $result->organizationId);
    self::assertSame(self::EQUIPMENT_ID, $result->equipmentId);
    self::assertSame(self::FACILITY_ID, $result->facilityId);
    self::assertSame('pass', $result->result);
    self::assertSame('submitted', $result->status);
    self::assertSame($performedAt->format('c'), $result->performedAt);
    self::assertSame('user', $result->inspectorType);
    self::assertSame('Jane Doe', $result->inspectorName);
    self::assertSame('user-123', $result->inspectorUserId);
    self::assertNull($result->inspectorOrganizationName);
    self::assertSame(self::CHECKLIST_ID, $result->checklistId);
    self::assertSame('All good', $result->notes);
    self::assertSame('signature-blob', $result->signature);
    self::assertSame(3, $result->nonConformitiesCount);
    self::assertSame($createdAt, $result->createdAt);
    self::assertSame($updatedAt, $result->updatedAt);
    self::assertSame('SN-001', $result->equipmentSerialNumber);
    self::assertSame('Main Warehouse', $result->facilityName);
    self::assertSame('Monthly Checklist', $result->checklistName);
    self::assertSame($profile, $result->inspectorProfile);
  }

  #[Test]
  public function itSkipsAssociationLookupsWhenTheInspectionHasNoFacilityOrChecklist(): void
  {
    $inspection = Inspection::reconstitute(
      id: InspectionId::fromString(self::INSPECTION_ID),
      organizationId: InspectionOrganizationId::fromString(self::ORGANIZATION_ID),
      references: new InspectionReferences(
        equipmentId: InspectionEquipmentId::fromString(self::EQUIPMENT_ID),
        inspector: Inspector::reconstitute(InspectorType::EXTERNAL, 'Acme Auditor', null, 'Acme Ltd'),
        facilityId: null,
        checklistId: null,
      ),
      finding: new InspectionFinding(
        result: InspectionResult::FAIL,
        status: InspectionStatus::DRAFT,
        performedAt: new DateTimeImmutable('2026-02-01T12:00:00+00:00'),
        notes: null,
        signature: null,
      ),
      createdAt: new DateTimeImmutable('2026-02-01T12:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-02-01T12:00:00+00:00'),
    );

    $inspectionRepository = $this->createStub(InspectionRepositoryPort::class);
    $inspectionRepository->method('findById')->willReturn($inspection);

    $nonConformityRepository = $this->createStub(NonConformityRepositoryPort::class);
    $nonConformityRepository->method('countByInspectionId')->willReturn(0);

    // Equipment serial is unresolved: the map omits the key, so the handler falls back to null.
    $equipmentNaming = $this->createStub(EquipmentNamingPort::class);
    $equipmentNaming->method('findSerialNumbersByIds')->willReturn([]);

    $facilityNaming = $this->createMock(FacilityNamingPort::class);
    $facilityNaming->expects(self::never())->method('findNamesByIds');

    $checklistRepository = $this->createMock(ChecklistRepositoryPort::class);
    $checklistRepository->expects(self::never())->method('findNamesByIds');

    $users = $this->createMock(UserDirectoryPort::class);
    $users->expects(self::never())->method('findById');
    $users->expects(self::never())->method('findByIds');

    $handler = new GetInspectionHandler(
      $inspectionRepository,
      $nonConformityRepository,
      $equipmentNaming,
      $facilityNaming,
      $checklistRepository,
      users: $users,
    );

    $result = $handler(new GetInspectionQuery(self::ORGANIZATION_ID, self::INSPECTION_ID));

    self::assertNull($result->facilityId);
    self::assertNull($result->checklistId);
    self::assertNull($result->notes);
    self::assertNull($result->signature);
    self::assertSame('external', $result->inspectorType);
    self::assertSame('Acme Auditor', $result->inspectorName);
    self::assertNull($result->inspectorUserId);
    self::assertSame('Acme Ltd', $result->inspectorOrganizationName);
    self::assertSame(0, $result->nonConformitiesCount);
    self::assertNull($result->equipmentSerialNumber);
    self::assertNull($result->facilityName);
    self::assertNull($result->checklistName);
    self::assertNull($result->inspectorProfile);
  }

  #[Test]
  #[DataProvider('unavailableProfileProvider')]
  public function itPreservesTheStoredInspectorWhenTheAccountIsMissingOrUnavailable(bool $directoryFails): void
  {
    $inspection = Inspection::create(
      id: InspectionId::fromString(self::INSPECTION_ID),
      organizationId: InspectionOrganizationId::fromString(self::ORGANIZATION_ID),
      equipmentId: InspectionEquipmentId::fromString(self::EQUIPMENT_ID),
      inspector: Inspector::forUser('user-123', 'Retained inspector'),
      result: InspectionResult::PASS,
      performedAt: new DateTimeImmutable('2026-01-15T10:00:00+00:00'),
    );
    $inspections = $this->createStub(InspectionRepositoryPort::class);
    $inspections->method('findById')->willReturn($inspection);
    $users = $this->createMock(UserDirectoryPort::class);
    $lookup = $users->expects(self::once())->method('findById')->with('user-123');
    if ($directoryFails) {
      $lookup->willThrowException(new RuntimeException('Account directory unavailable.'));
    } else {
      $lookup->willReturn(null);
    }

    $handler = new GetInspectionHandler($inspections, $this->createStub(NonConformityRepositoryPort::class), $this->createStub(EquipmentNamingPort::class), $this->createStub(FacilityNamingPort::class), $this->createStub(ChecklistRepositoryPort::class), users: $users);
    $result = $handler->__invoke(new GetInspectionQuery(self::ORGANIZATION_ID, self::INSPECTION_ID));

    self::assertSame(self::INSPECTION_ID, $result->inspectionId);
    self::assertSame('user-123', $result->inspectorUserId);
    self::assertSame('Retained inspector', $result->inspectorName);
    self::assertNull($result->inspectorProfile);
  }

  /**
   * @return array<string, array{bool}>
   */
  public static function unavailableProfileProvider(): array
  {
    return ['missing account' => [false], 'directory failure' => [true]];
  }

  #[Test]
  public function itThrowsWhenTheInspectionDoesNotExist(): void
  {
    $inspectionRepository = $this->createStub(InspectionRepositoryPort::class);
    $inspectionRepository->method('findById')->willReturn(null);

    $handler = new GetInspectionHandler(
      $inspectionRepository,
      $this->createStub(NonConformityRepositoryPort::class),
      $this->createStub(EquipmentNamingPort::class),
      $this->createStub(FacilityNamingPort::class),
      $this->createStub(ChecklistRepositoryPort::class),
    );

    $this->expectException(InspectionNotFoundException::class);

    $handler(new GetInspectionQuery(self::ORGANIZATION_ID, self::INSPECTION_ID));
  }

  #[Test]
  public function itThrowsWhenTheInspectionBelongsToAnotherOrganization(): void
  {
    $inspection = Inspection::reconstitute(
      id: InspectionId::fromString(self::INSPECTION_ID),
      organizationId: InspectionOrganizationId::fromString(self::ORGANIZATION_ID),
      references: new InspectionReferences(
        equipmentId: InspectionEquipmentId::fromString(self::EQUIPMENT_ID),
        inspector: Inspector::reconstitute(InspectorType::USER, 'Jane Doe', 'user-123', null),
      ),
      finding: new InspectionFinding(
        result: InspectionResult::PASS,
        status: InspectionStatus::SUBMITTED,
        performedAt: new DateTimeImmutable('2026-01-15T10:00:00+00:00'),
      ),
      createdAt: new DateTimeImmutable('2026-01-10T08:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-01-12T09:30:00+00:00'),
    );

    $inspectionRepository = $this->createStub(InspectionRepositoryPort::class);
    $inspectionRepository->method('findById')->willReturn($inspection);

    $handler = new GetInspectionHandler(
      $inspectionRepository,
      $this->createStub(NonConformityRepositoryPort::class),
      $this->createStub(EquipmentNamingPort::class),
      $this->createStub(FacilityNamingPort::class),
      $this->createStub(ChecklistRepositoryPort::class),
    );

    $this->expectException(InspectionNotFoundException::class);

    $handler(new GetInspectionQuery(self::OTHER_ORGANIZATION_ID, self::INSPECTION_ID));
  }

  #[Test]
  public function itThrowsInvalidArgumentWhenTheInspectionIdIsNotAUuid(): void
  {
    $handler = new GetInspectionHandler(
      $this->createStub(InspectionRepositoryPort::class),
      $this->createStub(NonConformityRepositoryPort::class),
      $this->createStub(EquipmentNamingPort::class),
      $this->createStub(FacilityNamingPort::class),
      $this->createStub(ChecklistRepositoryPort::class),
    );

    $this->expectException(InvalidValueException::class);

    $handler(new GetInspectionQuery(self::ORGANIZATION_ID, 'not-a-uuid'));
  }
}
