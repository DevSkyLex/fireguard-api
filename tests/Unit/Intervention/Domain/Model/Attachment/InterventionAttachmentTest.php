<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Domain\Model\Attachment;

use DateTimeImmutable;
use Intervention\Domain\Model\Attachment\InterventionAttachment;
use Intervention\Domain\ValueObject\{InterventionAttachmentId, InterventionAttachmentKind};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test InterventionAttachment.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InterventionAttachment::class)]
final class InterventionAttachmentTest extends TestCase
{
  private const string ATTACHMENT_ID = '550e8400-e29b-41d4-a716-446655440000';

  private const string INTERVENTION_ID = 'intervention-42';

  #[Test]
  public function testCreateExposesTheProvidedStateAndStampsUploadTime(): void
  {
    $before = new DateTimeImmutable();

    $attachment = InterventionAttachment::create(
      InterventionAttachmentId::fromString(self::ATTACHMENT_ID),
      self::INTERVENTION_ID,
      'evidence.jpg',
      '/storage/evidence.jpg',
      'image/jpeg',
      2048,
      'Before repair',
    );

    self::assertSame(self::ATTACHMENT_ID, (string) $attachment->id());
    self::assertSame(self::INTERVENTION_ID, $attachment->interventionId());
    self::assertSame('evidence.jpg', $attachment->fileName());
    self::assertSame('/storage/evidence.jpg', $attachment->storagePath());
    self::assertSame('image/jpeg', $attachment->mimeType());
    self::assertSame(2048, $attachment->size());
    self::assertSame('Before repair', $attachment->label());
    self::assertGreaterThanOrEqual($before, $attachment->uploadedAt());
  }

  #[Test]
  public function testCreateDefaultsTheLabelToNull(): void
  {
    $attachment = InterventionAttachment::create(
      InterventionAttachmentId::fromString(self::ATTACHMENT_ID),
      self::INTERVENTION_ID,
      'report.pdf',
      '/storage/report.pdf',
      'application/pdf',
      1024,
    );

    self::assertNull($attachment->label());
  }

  #[Test]
  public function testReconstitutePreservesThePersistedUploadTimestamp(): void
  {
    $uploadedAt = new DateTimeImmutable('2026-01-02T03:04:05+00:00');

    $attachment = InterventionAttachment::reconstitute(
      InterventionAttachmentId::fromString(self::ATTACHMENT_ID),
      self::INTERVENTION_ID,
      'photo.png',
      '/storage/photo.png',
      'image/png',
      512,
      $uploadedAt,
      'Site',
    );

    self::assertSame($uploadedAt, $attachment->uploadedAt());
    self::assertSame('photo.png', $attachment->fileName());
    self::assertSame('Site', $attachment->label());
  }

  #[Test]
  public function testCreateDefaultsTheKindToFile(): void
  {
    $attachment = InterventionAttachment::create(
      InterventionAttachmentId::fromString(self::ATTACHMENT_ID),
      self::INTERVENTION_ID,
      'evidence.jpg',
      '/storage/evidence.jpg',
      'image/jpeg',
      2048,
    );

    self::assertSame(InterventionAttachmentKind::FILE, $attachment->kind());
  }

  #[Test]
  public function testCreateAcceptsTheSignatureKind(): void
  {
    $attachment = InterventionAttachment::create(
      id: InterventionAttachmentId::fromString(self::ATTACHMENT_ID),
      interventionId: self::INTERVENTION_ID,
      fileName: 'signature.png',
      storagePath: '/storage/signature.png',
      mimeType: 'image/png',
      size: 1024,
      kind: InterventionAttachmentKind::SIGNATURE,
    );

    self::assertSame(InterventionAttachmentKind::SIGNATURE, $attachment->kind());
  }

  #[Test]
  public function testReconstitutePreservesThePersistedKind(): void
  {
    $attachment = InterventionAttachment::reconstitute(
      id: InterventionAttachmentId::fromString(self::ATTACHMENT_ID),
      interventionId: self::INTERVENTION_ID,
      fileName: 'signature.png',
      storagePath: '/storage/signature.png',
      mimeType: 'image/png',
      size: 1024,
      uploadedAt: new DateTimeImmutable('2026-01-02T03:04:05+00:00'),
      kind: InterventionAttachmentKind::SIGNATURE,
    );

    self::assertSame(InterventionAttachmentKind::SIGNATURE, $attachment->kind());
  }
}
