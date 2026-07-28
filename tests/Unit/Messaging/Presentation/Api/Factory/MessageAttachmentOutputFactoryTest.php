<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Presentation\Api\Factory;

use DateTimeImmutable;
use Messaging\Domain\Model\Attachment\MessagingAttachment;
use Messaging\Domain\ValueObject\MessagingAttachmentId;
use Messaging\Presentation\Api\Factory\MessageAttachmentOutputFactory;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test MessageAttachmentOutputFactoryTest.
 *
 * The uploader is exposed as an organization-scoped member IRI and the binary
 * lives behind a dedicated `/content` sub-resource — the storage path must
 * never leak into the payload.
 *
 * @category Factory Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MessageAttachmentOutputFactory::class)]
final class MessageAttachmentOutputFactoryTest extends TestCase
{
  // #region Constants
  private const string ATTACHMENT_ID = '550e8400-e29b-41d4-a716-446655482001';

  private const string MESSAGE_ID = '550e8400-e29b-41d4-a716-446655482002';

  private const string CONVERSATION_ID = '550e8400-e29b-41d4-a716-446655482003';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655482004';

  private const string MEMBER_ID = '550e8400-e29b-41d4-a716-446655482005';
  // #endregion

  // #region Methods
  #[Test]
  public function testFromAttachmentBuildsEveryIriFromTheAggregate(): void
  {
    $output = new MessageAttachmentOutputFactory()->fromAttachment($this->attachment('Plan'));

    self::assertSame(self::ATTACHMENT_ID, $output->id);
    self::assertSame('/api/messages/' . self::MESSAGE_ID, $output->message);
    self::assertSame('/api/conversations/' . self::CONVERSATION_ID, $output->conversation);
    self::assertSame(
      '/api/organizations/' . self::ORGANIZATION_ID . '/members/' . self::MEMBER_ID,
      $output->uploadedByMember,
    );
    self::assertSame(
      '/api/messaging-attachments/' . self::ATTACHMENT_ID . '/content',
      $output->contentUrl,
    );
  }

  #[Test]
  public function testFromAttachmentCarriesTheFileMetadataWithoutTheStoragePath(): void
  {
    $output = new MessageAttachmentOutputFactory()->fromAttachment($this->attachment('Plan'));

    self::assertSame('plan.pdf', $output->fileName);
    self::assertSame('application/pdf', $output->mimeType);
    self::assertSame(2048, $output->size);
    self::assertSame('Plan', $output->label);
    self::assertSame('2026-05-06T07:08:09+00:00', $output->uploadedAt);
  }

  #[Test]
  public function testFromAttachmentKeepsAnUnlabelledAttachmentNull(): void
  {
    $output = new MessageAttachmentOutputFactory()->fromAttachment($this->attachment());

    self::assertNull($output->label);
  }

  private function attachment(?string $label = null): MessagingAttachment
  {
    return MessagingAttachment::reconstitute(
      id: MessagingAttachmentId::fromString(self::ATTACHMENT_ID),
      messageId: self::MESSAGE_ID,
      conversationId: self::CONVERSATION_ID,
      organizationId: self::ORGANIZATION_ID,
      uploadedByMemberId: self::MEMBER_ID,
      fileName: 'plan.pdf',
      storagePath: 'messaging/2026/plan.pdf',
      mimeType: 'application/pdf',
      size: 2048,
      uploadedAt: new DateTimeImmutable('2026-05-06T07:08:09+00:00'),
      label: $label,
    );
  }
  // #endregion
}
