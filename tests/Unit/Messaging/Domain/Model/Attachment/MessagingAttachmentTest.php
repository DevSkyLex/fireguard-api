<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Domain\Model\Attachment;

use DateTimeImmutable;
use Messaging\Domain\Model\Attachment\MessagingAttachment;
use Messaging\Domain\ValueObject\MessagingAttachmentId;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test MessagingAttachment.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MessagingAttachment::class)]
final class MessagingAttachmentTest extends TestCase
{
  private const string ID = '66666666-6666-4666-8666-666666666666';

  #[Test]
  public function createExposesTheProvidedStateAndStampsUploadedAt(): void
  {
    $id = MessagingAttachmentId::fromString(self::ID);

    $attachment = MessagingAttachment::create(
      id: $id,
      messageId: 'msg-1',
      conversationId: 'conv-1',
      organizationId: 'org-1',
      uploadedByMemberId: 'member-1',
      fileName: 'report.pdf',
      storagePath: 'org-1/conv-1/report.pdf',
      mimeType: 'application/pdf',
      size: 2048,
      label: 'Inspection report',
    );

    self::assertSame($id, $attachment->id());
    self::assertSame('msg-1', $attachment->messageId());
    self::assertSame('conv-1', $attachment->conversationId());
    self::assertSame('org-1', $attachment->organizationId());
    self::assertSame('member-1', $attachment->uploadedByMemberId());
    self::assertSame('report.pdf', $attachment->fileName());
    self::assertSame('org-1/conv-1/report.pdf', $attachment->storagePath());
    self::assertSame('application/pdf', $attachment->mimeType());
    self::assertSame(2048, $attachment->size());
    self::assertSame('Inspection report', $attachment->label());
    self::assertInstanceOf(DateTimeImmutable::class, $attachment->uploadedAt());
  }

  #[Test]
  public function createDefaultsTheLabelToNull(): void
  {
    $attachment = MessagingAttachment::create(
      id: MessagingAttachmentId::fromString(self::ID),
      messageId: 'msg-1',
      conversationId: 'conv-1',
      organizationId: 'org-1',
      uploadedByMemberId: 'member-1',
      fileName: 'photo.jpg',
      storagePath: 'org-1/conv-1/photo.jpg',
      mimeType: 'image/jpeg',
      size: 512,
    );

    self::assertNull($attachment->label());
  }

  #[Test]
  public function reconstitutePreservesThePersistedUploadedAt(): void
  {
    $uploadedAt = new DateTimeImmutable('2026-01-15T10:00:00+00:00');

    $attachment = MessagingAttachment::reconstitute(
      MessagingAttachmentId::fromString(self::ID),
      'msg-1',
      'conv-1',
      'org-1',
      'member-1',
      'notes.txt',
      'org-1/conv-1/notes.txt',
      'text/plain',
      64,
      $uploadedAt,
      'Notes',
    );

    self::assertSame($uploadedAt, $attachment->uploadedAt());
    self::assertSame('Notes', $attachment->label());
    self::assertSame(64, $attachment->size());
  }
}
