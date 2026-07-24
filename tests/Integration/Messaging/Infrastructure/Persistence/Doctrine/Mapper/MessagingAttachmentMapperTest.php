<?php

declare(strict_types=1);

namespace Tests\Integration\Messaging\Infrastructure\Persistence\Doctrine\Mapper;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Messaging\Domain\Model\Attachment\MessagingAttachment;
use Messaging\Domain\ValueObject\MessagingAttachmentId;
use Messaging\Infrastructure\Persistence\Doctrine\Mapper\MessagingAttachmentMapper;
use Messaging\Infrastructure\Persistence\Doctrine\Record\{MessagingAttachmentRecord, MessagingConversationRecord, MessagingMessageRecord};
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test MessagingAttachmentMapper.
 *
 * `MessagingAttachmentMapper` is a stateless, all-static mapper used statically
 * by `MessagingAttachmentRepository` (never injected), so it is exercised here
 * through real-database round-trips rather than a container-fetched service: a
 * record is populated via `toRecord`, wired to a persisted message, saved with
 * the real `main` entity manager, re-fetched, and mapped back with `toDomain` —
 * which additionally validates that `toDomain` reads the owning message id from
 * the hydrated association and that the nullable `label` column round-trips both
 * present and null.
 *
 * @category Mapper Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MessagingAttachmentMapper::class)]
final class MessagingAttachmentMapperTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = '990e8400-e29b-41d4-a716-4466554408b1';

  private const string CONVERSATION_ID = '990e8400-e29b-41d4-a716-4466554408b2';

  private const string MESSAGE_ID = '990e8400-e29b-41d4-a716-4466554408b3';

  private const string LABELED_ATTACHMENT_ID = '990e8400-e29b-41d4-a716-4466554408b4';

  private const string UNLABELED_ATTACHMENT_ID = '990e8400-e29b-41d4-a716-4466554408b5';

  private const string MEMBER_ID = '990e8400-e29b-41d4-a716-4466554408b9';

  private EntityManagerInterface $entityManager;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    $this->createOrganization();
    $this->createConversation();
    $this->createMessage();
    $this->entityManager->flush();
    $this->entityManager->clear();
  }

  protected function tearDown(): void
  {
    $this->cleanup();
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testToRecordCopiesEveryColumnFromTheAggregate(): void
  {
    $uploadedAt = new DateTimeImmutable('2026-01-15T10:00:00+00:00');

    $attachment = MessagingAttachment::reconstitute(
      id: MessagingAttachmentId::fromString(self::LABELED_ATTACHMENT_ID),
      messageId: self::MESSAGE_ID,
      conversationId: self::CONVERSATION_ID,
      organizationId: self::ORGANIZATION_ID,
      uploadedByMemberId: self::MEMBER_ID,
      fileName: 'report.pdf',
      storagePath: 'messaging/2026/01/report.pdf',
      mimeType: 'application/pdf',
      size: 20480,
      uploadedAt: $uploadedAt,
      label: 'Q1 report',
    );

    $record = MessagingAttachmentMapper::toRecord($attachment);

    self::assertSame(self::LABELED_ATTACHMENT_ID, $record->id);
    self::assertSame(self::CONVERSATION_ID, $record->conversationId);
    self::assertSame(self::ORGANIZATION_ID, $record->organizationId);
    self::assertSame(self::MEMBER_ID, $record->uploadedByMemberId);
    self::assertSame('report.pdf', $record->fileName);
    self::assertSame('messaging/2026/01/report.pdf', $record->storagePath);
    self::assertSame('application/pdf', $record->mimeType);
    self::assertSame(20480, $record->size);
    self::assertSame('Q1 report', $record->label);
    self::assertSame($uploadedAt, $record->uploadedAt);
    // toRecord neither resolves the message association nor touches revision.
    self::assertNull($record->message);
    self::assertSame(1, $record->revision);
  }

  #[Test]
  public function testToRecordThenToDomainRoundTripsThroughTheDatabase(): void
  {
    $uploadedAt = new DateTimeImmutable('2026-01-15T10:30:00+00:00');

    $attachment = MessagingAttachment::reconstitute(
      id: MessagingAttachmentId::fromString(self::LABELED_ATTACHMENT_ID),
      messageId: self::MESSAGE_ID,
      conversationId: self::CONVERSATION_ID,
      organizationId: self::ORGANIZATION_ID,
      uploadedByMemberId: self::MEMBER_ID,
      fileName: 'plan.dwg',
      storagePath: 'messaging/2026/01/plan.dwg',
      mimeType: 'image/vnd.dwg',
      size: 1048576,
      uploadedAt: $uploadedAt,
      label: 'Site plan',
    );

    $record = MessagingAttachmentMapper::toRecord($attachment);
    // toRecord leaves the FK association null (see class docblock); wire it to
    // the persisted message so the row satisfies the NOT NULL message_id FK.
    $record->message = $this->entityManager->getReference(MessagingMessageRecord::class, self::MESSAGE_ID);
    $this->entityManager->persist($record);
    $this->entityManager->flush();
    $this->entityManager->clear();

    $fetched = $this->entityManager->find(MessagingAttachmentRecord::class, self::LABELED_ATTACHMENT_ID);
    self::assertInstanceOf(MessagingAttachmentRecord::class, $fetched);

    $mapped = MessagingAttachmentMapper::toDomain($fetched);

    self::assertSame(self::LABELED_ATTACHMENT_ID, (string) $mapped->id());
    // messageId is read from the hydrated association, not a denormalized column.
    self::assertSame(self::MESSAGE_ID, $mapped->messageId());
    self::assertSame(self::CONVERSATION_ID, $mapped->conversationId());
    self::assertSame(self::ORGANIZATION_ID, $mapped->organizationId());
    self::assertSame(self::MEMBER_ID, $mapped->uploadedByMemberId());
    self::assertSame('plan.dwg', $mapped->fileName());
    self::assertSame('messaging/2026/01/plan.dwg', $mapped->storagePath());
    self::assertSame('image/vnd.dwg', $mapped->mimeType());
    self::assertSame(1048576, $mapped->size());
    self::assertSame('Site plan', $mapped->label());
    self::assertSame($uploadedAt->format('Y-m-d H:i:s'), $mapped->uploadedAt()->format('Y-m-d H:i:s'));
  }

  #[Test]
  public function testToDomainKeepsANullLabelNull(): void
  {
    $attachment = MessagingAttachment::reconstitute(
      id: MessagingAttachmentId::fromString(self::UNLABELED_ATTACHMENT_ID),
      messageId: self::MESSAGE_ID,
      conversationId: self::CONVERSATION_ID,
      organizationId: self::ORGANIZATION_ID,
      uploadedByMemberId: self::MEMBER_ID,
      fileName: 'photo.jpg',
      storagePath: 'messaging/2026/01/photo.jpg',
      mimeType: 'image/jpeg',
      size: 4096,
      uploadedAt: new DateTimeImmutable('2026-01-15T11:00:00+00:00'),
      label: null,
    );

    $record = MessagingAttachmentMapper::toRecord($attachment);
    self::assertNull($record->label);

    $record->message = $this->entityManager->getReference(MessagingMessageRecord::class, self::MESSAGE_ID);
    $this->entityManager->persist($record);
    $this->entityManager->flush();
    $this->entityManager->clear();

    $fetched = $this->entityManager->find(MessagingAttachmentRecord::class, self::UNLABELED_ATTACHMENT_ID);
    self::assertInstanceOf(MessagingAttachmentRecord::class, $fetched);

    $mapped = MessagingAttachmentMapper::toDomain($fetched);

    self::assertSame(self::UNLABELED_ATTACHMENT_ID, (string) $mapped->id());
    self::assertNull($mapped->label());
    self::assertSame('photo.jpg', $mapped->fileName());
  }

  #[Test]
  public function testToDomainThrowsWhenTheRecordHasNoMessage(): void
  {
    $record = new MessagingAttachmentRecord();
    // message stays null: toDomain cannot derive the owning message id.

    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('Attachment record must reference a message.');

    MessagingAttachmentMapper::toDomain($record);
  }

  private function createOrganization(): void
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $organization->name = 'Messaging Attachment Mapper Test';
    $organization->slug = 'messaging-attachment-mapper-test';
    $organization->ownerUserId = self::MEMBER_ID;
    $organization->createdByUserId = self::MEMBER_ID;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;
    $this->entityManager->persist($organization);
  }

  private function createConversation(): void
  {
    $conversation = new MessagingConversationRecord();
    $conversation->id = self::CONVERSATION_ID;
    $conversation->organization = $this->entityManager->getReference(OrganizationRecord::class, self::ORGANIZATION_ID);
    $conversation->subjectType = 'facility';
    $conversation->subjectId = '990e8400-e29b-41d4-a716-4466554408c0';
    $conversation->createdAt = new DateTimeImmutable('2026-01-02T00:00:00+00:00');
    $conversation->updatedAt = $conversation->createdAt;
    $this->entityManager->persist($conversation);
  }

  private function createMessage(): void
  {
    $message = new MessagingMessageRecord();
    $message->id = self::MESSAGE_ID;
    $message->conversation = $this->entityManager->getReference(MessagingConversationRecord::class, self::CONVERSATION_ID);
    $message->organizationId = self::ORGANIZATION_ID;
    $message->authorMemberId = self::MEMBER_ID;
    $message->body = 'Please review the attached files.';
    $message->createdAt = new DateTimeImmutable('2026-01-03T00:00:00+00:00');
    $message->updatedAt = $message->createdAt;
    $this->entityManager->persist($message);
  }

  private function cleanup(): void
  {
    $connection = $this->entityManager->getConnection();
    $connection->executeStatement(
      'DELETE FROM messaging_attachments WHERE organization_id = :organizationId',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $connection->executeStatement(
      'DELETE FROM messaging_messages WHERE organization_id = :organizationId',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $connection->executeStatement(
      'DELETE FROM messaging_conversations WHERE organization_id = :organizationId',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $connection->executeStatement(
      'DELETE FROM organizations WHERE id = :organizationId',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $this->entityManager->clear();
  }
}
