<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Domain\Model\Attachment;

use DateTimeImmutable;
use Equipment\Domain\Model\Attachment\EquipmentAttachment;
use Equipment\Domain\ValueObject\{AttachmentId, EquipmentId};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test EquipmentAttachmentTest.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(EquipmentAttachment::class)]
final class EquipmentAttachmentTest extends TestCase
{
  private const string ATTACHMENT_ID = '550e8400-e29b-41d4-a716-446655441000';

  private const string EQUIPMENT_ID = '550e8400-e29b-41d4-a716-446655441001';

  #[Test]
  public function itCreatesAttachmentWithGeneratedTimestamp(): void
  {
    $attachment = EquipmentAttachment::create(
      id: AttachmentId::fromString(self::ATTACHMENT_ID),
      equipmentId: EquipmentId::fromString(self::EQUIPMENT_ID),
      fileName: 'manual.pdf',
      storagePath: 'equipment/manual.pdf',
      mimeType: 'application/pdf',
      size: 2048,
      label: 'User manual',
    );

    self::assertSame(self::ATTACHMENT_ID, $attachment->id()->value);
    self::assertSame(self::EQUIPMENT_ID, $attachment->equipmentId()->value);
    self::assertSame('manual.pdf', $attachment->fileName());
    self::assertSame('equipment/manual.pdf', $attachment->storagePath());
    self::assertSame('application/pdf', $attachment->mimeType());
    self::assertSame(2048, $attachment->size());
    self::assertSame('User manual', $attachment->label());
    self::assertInstanceOf(DateTimeImmutable::class, $attachment->uploadedAt());
  }

  #[Test]
  public function itDefaultsLabelToNull(): void
  {
    $attachment = EquipmentAttachment::create(
      id: AttachmentId::fromString(self::ATTACHMENT_ID),
      equipmentId: EquipmentId::fromString(self::EQUIPMENT_ID),
      fileName: 'photo.jpg',
      storagePath: 'equipment/photo.jpg',
      mimeType: 'image/jpeg',
      size: 512,
    );

    self::assertNull($attachment->label());
  }

  #[Test]
  public function itReconstitutesWithProvidedTimestamp(): void
  {
    $uploadedAt = new DateTimeImmutable('2026-01-15T10:00:00+00:00');

    $attachment = EquipmentAttachment::reconstitute(
      id: AttachmentId::fromString(self::ATTACHMENT_ID),
      equipmentId: EquipmentId::fromString(self::EQUIPMENT_ID),
      fileName: 'report.pdf',
      storagePath: 'equipment/report.pdf',
      mimeType: 'application/pdf',
      size: 4096,
      uploadedAt: $uploadedAt,
      label: null,
    );

    self::assertSame($uploadedAt, $attachment->uploadedAt());
    self::assertSame(4096, $attachment->size());
  }
}
