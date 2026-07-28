<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Infrastructure\Persistence\Doctrine\Mapper;

use DateTimeImmutable;
use Inspection\Domain\Model\NonConformity\NonConformity;
use Inspection\Domain\ValueObject\{NonConformityId, NonConformityInspectionId, NonConformitySeverity, NonConformityStatus};
use Inspection\Infrastructure\Persistence\Doctrine\Mapper\NonConformityMapper;
use Inspection\Infrastructure\Persistence\Doctrine\Record\{InspectionRecord, NonConformityRecord};
use LogicException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test NonConformityMapperTest.
 *
 * @category Mapper Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(NonConformityMapper::class)]
final class NonConformityMapperTest extends TestCase
{
  private const string NON_CONFORMITY_ID = '550e8400-e29b-41d4-a716-446655440301';

  private const string INSPECTION_ID = '550e8400-e29b-41d4-a716-446655440302';

  #[Test]
  public function testToDomainRebuildsTheAggregate(): void
  {
    $nonConformity = NonConformityMapper::toDomain($this->record());

    self::assertSame(self::NON_CONFORMITY_ID, (string) $nonConformity->id());
    self::assertSame(self::INSPECTION_ID, (string) $nonConformity->inspectionId());
    self::assertSame('Blocked emergency exit', $nonConformity->description());
    self::assertSame(NonConformitySeverity::CRITICAL, $nonConformity->severity());
    self::assertSame(NonConformityStatus::OPEN, $nonConformity->status());
    self::assertSame('2026-02-01T08:00:00+00:00', $nonConformity->dueAt()?->format('c'));
    self::assertNull($nonConformity->resolvedAt());
    self::assertSame('Reported by the site manager', $nonConformity->notes());
  }

  #[Test]
  public function testToDomainRejectsARecordWithoutAnInspection(): void
  {
    $record = $this->record();
    $record->inspection = null;

    $this->expectException(LogicException::class);

    NonConformityMapper::toDomain($record);
  }

  #[Test]
  public function testToRecordFlattensTheAggregate(): void
  {
    $record = NonConformityMapper::toRecord($this->nonConformity());

    self::assertSame(self::NON_CONFORMITY_ID, $record->id);
    self::assertSame('Blocked emergency exit', $record->description);
    self::assertSame('critical', $record->severity);
    self::assertSame('done', $record->status);
    self::assertSame('2026-02-01T08:00:00+00:00', $record->dueAt?->format('c'));
    self::assertSame('2026-01-20T08:00:00+00:00', $record->resolvedAt?->format('c'));
    self::assertSame('Reported by the site manager', $record->notes);
    self::assertSame('2026-01-01T08:00:00+00:00', $record->createdAt->format('c'));
    self::assertSame('2026-01-02T08:00:00+00:00', $record->updatedAt->format('c'));
  }

  private function record(): NonConformityRecord
  {
    $inspection = new InspectionRecord();
    $inspection->id = self::INSPECTION_ID;

    $record = new NonConformityRecord();
    $record->id = self::NON_CONFORMITY_ID;
    $record->inspection = $inspection;
    $record->description = 'Blocked emergency exit';
    $record->severity = 'critical';
    $record->status = 'open';
    $record->dueAt = new DateTimeImmutable('2026-02-01T08:00:00+00:00');
    $record->resolvedAt = null;
    $record->notes = 'Reported by the site manager';
    $record->createdAt = new DateTimeImmutable('2026-01-01T08:00:00+00:00');
    $record->updatedAt = new DateTimeImmutable('2026-01-02T08:00:00+00:00');

    return $record;
  }

  private function nonConformity(): NonConformity
  {
    return NonConformity::reconstitute(
      id: NonConformityId::fromString(self::NON_CONFORMITY_ID),
      inspectionId: NonConformityInspectionId::fromString(self::INSPECTION_ID),
      description: 'Blocked emergency exit',
      severity: NonConformitySeverity::CRITICAL,
      status: NonConformityStatus::DONE,
      createdAt: new DateTimeImmutable('2026-01-01T08:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-01-02T08:00:00+00:00'),
      dueAt: new DateTimeImmutable('2026-02-01T08:00:00+00:00'),
      resolvedAt: new DateTimeImmutable('2026-01-20T08:00:00+00:00'),
      notes: 'Reported by the site manager',
    );
  }
}
