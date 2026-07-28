<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Infrastructure\Persistence\Doctrine\Mapper;

use DateTimeImmutable;
use Inspection\Domain\Model\Checklist\{Checklist, ChecklistItem};
use Inspection\Domain\ValueObject\{ChecklistId, ChecklistOrganizationId, ChecklistStatus};
use Inspection\Infrastructure\Persistence\Doctrine\Mapper\ChecklistMapper;
use Inspection\Infrastructure\Persistence\Doctrine\Record\{ChecklistItemRecord, ChecklistRecord};
use LogicException;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ChecklistMapperTest.
 *
 * @category Mapper Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ChecklistMapper::class)]
final class ChecklistMapperTest extends TestCase
{
  private const string CHECKLIST_ID = '550e8400-e29b-41d4-a716-446655440101';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440102';

  #[Test]
  public function testToDomainRebuildsTheAggregateWithItsItems(): void
  {
    $itemRecord = new ChecklistItemRecord();
    $itemRecord->id = 'item-1';
    $itemRecord->label = 'Check pressure gauge';
    $itemRecord->position = 1;
    $itemRecord->required = true;
    $itemRecord->description = 'Between 12 and 15 bar';

    $checklist = ChecklistMapper::toDomain($this->record(), [$itemRecord]);

    self::assertSame(self::CHECKLIST_ID, (string) $checklist->id());
    self::assertSame(self::ORGANIZATION_ID, (string) $checklist->organizationId());
    self::assertSame('Annual Safety Checklist', $checklist->name());
    self::assertSame('1.0', $checklist->version());
    self::assertSame('CHK-001', $checklist->referenceCode());
    self::assertSame(ChecklistStatus::ACTIVE, $checklist->status());
    self::assertCount(1, $checklist->items());
    self::assertSame('Check pressure gauge', $checklist->items()[0]->label());
    self::assertSame('Between 12 and 15 bar', $checklist->items()[0]->description());
  }

  #[Test]
  public function testToDomainRejectsARecordWithoutAnOrganization(): void
  {
    $record = $this->record();
    $record->organization = null;

    $this->expectException(LogicException::class);

    ChecklistMapper::toDomain($record);
  }

  #[Test]
  public function testToRecordCopiesTheScalarState(): void
  {
    $record = ChecklistMapper::toRecord($this->checklist());

    self::assertSame(self::CHECKLIST_ID, $record->id);
    self::assertSame('Annual Safety Checklist', $record->name);
    self::assertSame('CHK-001', $record->referenceCode);
    self::assertSame('1.0', $record->version);
    self::assertSame('archived', $record->status);
    self::assertSame('2026-01-01T08:00:00+00:00', $record->createdAt->format('c'));
    self::assertSame('2026-01-02T08:00:00+00:00', $record->updatedAt->format('c'));
  }

  #[Test]
  public function testToItemRecordsProjectsEveryItem(): void
  {
    $records = ChecklistMapper::toItemRecords($this->checklist());

    self::assertCount(1, $records);
    self::assertSame('item-1', $records[0]->id);
    self::assertSame('Check pressure gauge', $records[0]->label);
    self::assertSame(1, $records[0]->position);
    self::assertTrue($records[0]->required);
    self::assertSame('Between 12 and 15 bar', $records[0]->description);
  }

  private function record(): ChecklistRecord
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;

    $record = new ChecklistRecord();
    $record->id = self::CHECKLIST_ID;
    $record->organization = $organization;
    $record->name = 'Annual Safety Checklist';
    $record->referenceCode = 'CHK-001';
    $record->version = '1.0';
    $record->status = 'active';
    $record->createdAt = new DateTimeImmutable('2026-01-01T08:00:00+00:00');
    $record->updatedAt = new DateTimeImmutable('2026-01-02T08:00:00+00:00');

    return $record;
  }

  private function checklist(): Checklist
  {
    return Checklist::reconstitute(
      id: ChecklistId::fromString(self::CHECKLIST_ID),
      organizationId: ChecklistOrganizationId::fromString(self::ORGANIZATION_ID),
      name: 'Annual Safety Checklist',
      version: '1.0',
      status: ChecklistStatus::ARCHIVED,
      items: [ChecklistItem::reconstitute(
        id: 'item-1',
        label: 'Check pressure gauge',
        position: 1,
        required: true,
        description: 'Between 12 and 15 bar',
      )],
      createdAt: new DateTimeImmutable('2026-01-01T08:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-01-02T08:00:00+00:00'),
      referenceCode: 'CHK-001',
    );
  }
}
