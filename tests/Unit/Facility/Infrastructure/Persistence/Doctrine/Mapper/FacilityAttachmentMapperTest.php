<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Infrastructure\Persistence\Doctrine\Mapper;

use DateTimeImmutable;
use Facility\Domain\Model\Attachment\FacilityAttachment;
use Facility\Domain\ValueObject\{FacilityAttachmentId, FacilityId};
use Facility\Infrastructure\Persistence\Doctrine\Mapper\FacilityAttachmentMapper;
use Facility\Infrastructure\Persistence\Doctrine\Record\{FacilityAttachmentRecord, FacilityRecord};
use LogicException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test FacilityAttachmentMapperTest.
 *
 * The mapper reads the owning facility through the Doctrine association
 * rather than a plain column, so a record loaded without that relation
 * would otherwise map to an attachment pointing at nothing. That guard,
 * and the field-by-field round trip, are what this pins.
 *
 * @category Mapper Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(FacilityAttachmentMapper::class)]
final class FacilityAttachmentMapperTest extends TestCase
{
  // #region Constants
  private const string ATTACHMENT_ID = '550e8400-e29b-41d4-a716-446655476001';

  private const string FACILITY_ID = '550e8400-e29b-41d4-a716-446655476002';
  // #endregion

  // #region Methods
  #[Test]
  public function testToDomainRebuildsTheAttachmentFromItsRecord(): void
  {
    $attachment = FacilityAttachmentMapper::toDomain($this->record());

    self::assertSame(self::ATTACHMENT_ID, (string) $attachment->id());
    self::assertSame(self::FACILITY_ID, (string) $attachment->facilityId());
    self::assertSame('plan.pdf', $attachment->fileName());
    self::assertSame('facilities/plan.pdf', $attachment->storagePath());
    self::assertSame('application/pdf', $attachment->mimeType());
    self::assertSame(2048, $attachment->size());
    self::assertSame('Floor plan', $attachment->label());
    self::assertEquals(new DateTimeImmutable('2026-01-05T10:00:00+00:00'), $attachment->uploadedAt());
  }

  #[Test]
  public function testToDomainRefusesARecordWithoutItsFacility(): void
  {
    $record = $this->record();
    $record->facility = null;

    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('Attachment record must reference a facility.');

    FacilityAttachmentMapper::toDomain($record);
  }

  #[Test]
  public function testToRecordCopiesEveryPersistedField(): void
  {
    $record = FacilityAttachmentMapper::toRecord($this->attachment());

    self::assertSame(self::ATTACHMENT_ID, $record->id);
    self::assertSame('plan.pdf', $record->fileName);
    self::assertSame('facilities/plan.pdf', $record->storagePath);
    self::assertSame('application/pdf', $record->mimeType);
    self::assertSame(2048, $record->size);
    self::assertSame('Floor plan', $record->label);
    self::assertEquals(new DateTimeImmutable('2026-01-05T10:00:00+00:00'), $record->uploadedAt);
  }

  #[Test]
  public function testToRecordLeavesAnUnlabelledAttachmentNull(): void
  {
    $record = FacilityAttachmentMapper::toRecord($this->attachment(label: null));

    self::assertNull($record->label);
  }

  private function record(): FacilityAttachmentRecord
  {
    $facility = new FacilityRecord();
    $facility->id = self::FACILITY_ID;

    $record = new FacilityAttachmentRecord();
    $record->id = self::ATTACHMENT_ID;
    $record->facility = $facility;
    $record->fileName = 'plan.pdf';
    $record->storagePath = 'facilities/plan.pdf';
    $record->mimeType = 'application/pdf';
    $record->size = 2048;
    $record->label = 'Floor plan';
    $record->uploadedAt = new DateTimeImmutable('2026-01-05T10:00:00+00:00');

    return $record;
  }

  private function attachment(?string $label = 'Floor plan'): FacilityAttachment
  {
    return FacilityAttachment::reconstitute(
      id: FacilityAttachmentId::fromString(self::ATTACHMENT_ID),
      facilityId: FacilityId::fromString(self::FACILITY_ID),
      fileName: 'plan.pdf',
      storagePath: 'facilities/plan.pdf',
      mimeType: 'application/pdf',
      size: 2048,
      uploadedAt: new DateTimeImmutable('2026-01-05T10:00:00+00:00'),
      label: $label,
    );
  }
  // #endregion
}
