<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Infrastructure\Persistence\Doctrine\Mapper;

use DateTimeImmutable;
use Inspection\Domain\Model\Inspection\Inspection;
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
use Inspection\Infrastructure\Persistence\Doctrine\Mapper\InspectionMapper;
use Inspection\Infrastructure\Persistence\Doctrine\Record\InspectionRecord;
use LogicException;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test InspectionMapperTest.
 *
 * @category Mapper Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InspectionMapper::class)]
final class InspectionMapperTest extends TestCase
{
  private const string INSPECTION_ID = '550e8400-e29b-41d4-a716-446655440201';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440202';

  private const string EQUIPMENT_ID = '550e8400-e29b-41d4-a716-446655440203';

  private const string FACILITY_ID = '550e8400-e29b-41d4-a716-446655440204';

  private const string CHECKLIST_ID = '550e8400-e29b-41d4-a716-446655440205';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655440206';

  #[Test]
  public function testToDomainRebuildsTheAggregate(): void
  {
    $inspection = InspectionMapper::toDomain($this->record());

    self::assertSame(self::INSPECTION_ID, (string) $inspection->id());
    self::assertSame(self::ORGANIZATION_ID, (string) $inspection->organizationId());
    self::assertSame(self::EQUIPMENT_ID, (string) $inspection->equipmentId());
    self::assertSame(self::FACILITY_ID, (string) $inspection->facilityId());
    self::assertSame(self::CHECKLIST_ID, (string) $inspection->checklistId());
    self::assertSame(InspectorType::USER, $inspection->inspector()->type);
    self::assertSame('Inspector', $inspection->inspector()->name);
    self::assertSame(InspectionResult::PASS, $inspection->result());
    self::assertSame(InspectionStatus::DRAFT, $inspection->status());
    self::assertSame('Nothing to report', $inspection->notes());
    self::assertSame('signature-blob', $inspection->signature());
  }

  #[Test]
  public function testToDomainLeavesTheOptionalIdentifiersNullWhenAbsent(): void
  {
    $record = $this->record();
    $record->facilityId = null;
    $record->checklistId = null;

    $inspection = InspectionMapper::toDomain($record);

    self::assertNull($inspection->facilityId());
    self::assertNull($inspection->checklistId());
  }

  #[Test]
  public function testToDomainRejectsARecordWithoutAnOrganization(): void
  {
    $record = $this->record();
    $record->organization = null;

    $this->expectException(LogicException::class);

    InspectionMapper::toDomain($record);
  }

  #[Test]
  public function testToRecordFlattensTheAggregate(): void
  {
    $record = InspectionMapper::toRecord($this->inspection());

    self::assertSame(self::INSPECTION_ID, $record->id);
    self::assertSame(self::EQUIPMENT_ID, $record->equipmentId);
    self::assertSame(self::FACILITY_ID, $record->facilityId);
    self::assertSame(self::CHECKLIST_ID, $record->checklistId);
    self::assertSame('user', $record->inspectorType);
    self::assertSame('Inspector', $record->inspectorName);
    self::assertSame(self::USER_ID, $record->inspectorUserId);
    self::assertNull($record->inspectorOrganizationName);
    self::assertSame('pass', $record->result);
    self::assertSame('draft', $record->status);
    self::assertSame('Nothing to report', $record->notes);
    self::assertSame('signature-blob', $record->signature);
  }

  #[Test]
  public function testToRecordKeepsTheOptionalIdentifiersNull(): void
  {
    $record = InspectionMapper::toRecord($this->inspection(withOptionalIds: false));

    self::assertNull($record->facilityId);
    self::assertNull($record->checklistId);
  }

  private function record(): InspectionRecord
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;

    $record = new InspectionRecord();
    $record->id = self::INSPECTION_ID;
    $record->organization = $organization;
    $record->equipmentId = self::EQUIPMENT_ID;
    $record->facilityId = self::FACILITY_ID;
    $record->inspectorType = 'user';
    $record->inspectorName = 'Inspector';
    $record->inspectorUserId = self::USER_ID;
    $record->inspectorOrganizationName = null;
    $record->result = 'pass';
    $record->status = 'draft';
    $record->performedAt = new DateTimeImmutable('2026-01-01T08:00:00+00:00');
    $record->checklistId = self::CHECKLIST_ID;
    $record->notes = 'Nothing to report';
    $record->signature = 'signature-blob';
    $record->createdAt = new DateTimeImmutable('2026-01-01T09:00:00+00:00');
    $record->updatedAt = new DateTimeImmutable('2026-01-01T10:00:00+00:00');

    return $record;
  }

  private function inspection(bool $withOptionalIds = true): Inspection
  {
    return Inspection::reconstitute(
      id: InspectionId::fromString(self::INSPECTION_ID),
      organizationId: InspectionOrganizationId::fromString(self::ORGANIZATION_ID),
      equipmentId: InspectionEquipmentId::fromString(self::EQUIPMENT_ID),
      inspector: Inspector::forUser(self::USER_ID, 'Inspector'),
      result: InspectionResult::PASS,
      status: InspectionStatus::DRAFT,
      performedAt: new DateTimeImmutable('2026-01-01T08:00:00+00:00'),
      createdAt: new DateTimeImmutable('2026-01-01T09:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-01-01T10:00:00+00:00'),
      facilityId: $withOptionalIds ? InspectionFacilityId::fromString(self::FACILITY_ID) : null,
      checklistId: $withOptionalIds ? InspectionChecklistId::fromString(self::CHECKLIST_ID) : null,
      notes: 'Nothing to report',
      signature: 'signature-blob',
    );
  }
}
