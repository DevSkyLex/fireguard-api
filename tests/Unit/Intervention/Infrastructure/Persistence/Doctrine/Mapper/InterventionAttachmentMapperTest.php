<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Infrastructure\Persistence\Doctrine\Mapper;

use DateTimeImmutable;
use Intervention\Domain\Model\Attachment\InterventionAttachment;
use Intervention\Domain\ValueObject\InterventionAttachmentId;
use Intervention\Infrastructure\Persistence\Doctrine\Mapper\InterventionAttachmentMapper;
use Intervention\Infrastructure\Persistence\Doctrine\Record\{
  InterventionAttachmentRecord,
  InterventionRecord
};
use LogicException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test InterventionAttachmentMapperTest.
 *
 * The owning intervention reaches the domain object through the Doctrine
 * association, so a record hydrated without it must be rejected rather than
 * silently producing an orphaned attachment.
 *
 * @category Mapper Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InterventionAttachmentMapper::class)]
final class InterventionAttachmentMapperTest extends TestCase
{
  // #region Constants
  private const string ATTACHMENT_ID = '550e8400-e29b-41d4-a716-446655477001';

  private const string INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655477002';
  // #endregion

  // #region Methods
  #[Test]
  public function testToDomainRebuildsTheAttachmentFromItsRecord(): void
  {
    $attachment = InterventionAttachmentMapper::toDomain($this->record());

    self::assertSame(self::ATTACHMENT_ID, (string) $attachment->id());
    self::assertSame(self::INTERVENTION_ID, $attachment->interventionId());
    self::assertSame('report.pdf', $attachment->fileName());
    self::assertSame('interventions/report.pdf', $attachment->storagePath());
    self::assertSame('application/pdf', $attachment->mimeType());
    self::assertSame(4096, $attachment->size());
    self::assertSame('Site report', $attachment->label());
    self::assertEquals(new DateTimeImmutable('2026-01-06T11:00:00+00:00'), $attachment->uploadedAt());
  }

  #[Test]
  public function testToDomainRefusesARecordWithoutItsIntervention(): void
  {
    $record = $this->record();
    $record->intervention = null;

    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('Attachment record must reference an intervention.');

    InterventionAttachmentMapper::toDomain($record);
  }

  #[Test]
  public function testToRecordCopiesEveryPersistedField(): void
  {
    $record = InterventionAttachmentMapper::toRecord($this->attachment());

    self::assertSame(self::ATTACHMENT_ID, $record->id);
    self::assertSame('report.pdf', $record->fileName);
    self::assertSame('interventions/report.pdf', $record->storagePath);
    self::assertSame('application/pdf', $record->mimeType);
    self::assertSame(4096, $record->size);
    self::assertSame('Site report', $record->label);
    self::assertEquals(new DateTimeImmutable('2026-01-06T11:00:00+00:00'), $record->uploadedAt);
  }

  #[Test]
  public function testToRecordLeavesAnUnlabelledAttachmentNull(): void
  {
    self::assertNull(InterventionAttachmentMapper::toRecord($this->attachment(label: null))->label);
  }

  private function record(): InterventionAttachmentRecord
  {
    $intervention = new InterventionRecord();
    $intervention->id = self::INTERVENTION_ID;

    $record = new InterventionAttachmentRecord();
    $record->id = self::ATTACHMENT_ID;
    $record->intervention = $intervention;
    $record->fileName = 'report.pdf';
    $record->storagePath = 'interventions/report.pdf';
    $record->mimeType = 'application/pdf';
    $record->size = 4096;
    $record->label = 'Site report';
    $record->uploadedAt = new DateTimeImmutable('2026-01-06T11:00:00+00:00');

    return $record;
  }

  private function attachment(?string $label = 'Site report'): InterventionAttachment
  {
    return InterventionAttachment::reconstitute(
      id: InterventionAttachmentId::fromString(self::ATTACHMENT_ID),
      interventionId: self::INTERVENTION_ID,
      fileName: 'report.pdf',
      storagePath: 'interventions/report.pdf',
      mimeType: 'application/pdf',
      size: 4096,
      uploadedAt: new DateTimeImmutable('2026-01-06T11:00:00+00:00'),
      label: $label,
    );
  }
  // #endregion
}
