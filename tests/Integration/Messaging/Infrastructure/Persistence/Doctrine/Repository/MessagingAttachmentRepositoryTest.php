<?php

declare(strict_types=1);

namespace Tests\Integration\Messaging\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Messaging\Domain\Model\Attachment\MessagingAttachment;
use Messaging\Domain\Model\Message\Message;
use Messaging\Domain\Service\MentionExtractor;
use Messaging\Domain\ValueObject\{MessageId, MessagingAttachmentId};
use Messaging\Infrastructure\Persistence\Doctrine\Record\MessagingConversationRecord;
use Messaging\Infrastructure\Persistence\Doctrine\Repository\{MessagingAttachmentRepository, MessagingMessageRepository};
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_map;
use function mt_rand;
use function sprintf;

/**
 * Test MessagingAttachmentRepository.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MessagingAttachmentRepository::class)]
final class MessagingAttachmentRepositoryTest extends KernelTestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655447000';

  private const string CONVERSATION_ID = '550e8400-e29b-41d4-a716-446655447010';

  private EntityManagerInterface $entityManager;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->removeOrganization(self::ORG_ID);

    $organization = $this->createOrganization(self::ORG_ID);
    $this->createConversation(self::CONVERSATION_ID, $organization);
    $this->entityManager->flush();
  }

  protected function tearDown(): void
  {
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testSaveThenFindByIdRoundTripsThePersistedAttachment(): void
  {
    $messages = new MessagingMessageRepository($this->entityManager);
    $repository = new MessagingAttachmentRepository($this->entityManager);

    $message = $this->appendMessage($messages, 'With attachment');
    $id = $this->uuid();
    $attachment = $this->makeAttachment(
      $id,
      (string) $message->id(),
      new DateTimeImmutable('2026-01-01T10:00:00+00:00'),
      'report.pdf',
    );

    $repository->save($attachment);

    $found = $repository->findById(MessagingAttachmentId::fromString($id));

    self::assertInstanceOf(MessagingAttachment::class, $found);
    self::assertSame($id, (string) $found->id());
    self::assertSame((string) $message->id(), $found->messageId());
    self::assertSame(self::CONVERSATION_ID, $found->conversationId());
    self::assertSame('report.pdf', $found->fileName());
  }

  #[Test]
  public function testFindByIdReturnsNullForAnUnknownAttachment(): void
  {
    $repository = new MessagingAttachmentRepository($this->entityManager);

    self::assertNull($repository->findById(MessagingAttachmentId::fromString($this->uuid())));
  }

  #[Test]
  public function testFindByMessageIdsBatchesAttachmentsAndExcludesUnrelatedMessages(): void
  {
    $messages = new MessagingMessageRepository($this->entityManager);
    $repository = new MessagingAttachmentRepository($this->entityManager);

    $first = $this->appendMessage($messages, 'First');
    $second = $this->appendMessage($messages, 'Second');
    $unrelated = $this->appendMessage($messages, 'No attachment');

    $repository->save($this->makeAttachment($this->uuid(), (string) $first->id(), new DateTimeImmutable('2026-01-01T10:00:00+00:00'), 'a.pdf'));
    $repository->save($this->makeAttachment($this->uuid(), (string) $first->id(), new DateTimeImmutable('2026-01-01T10:01:00+00:00'), 'b.pdf'));
    $repository->save($this->makeAttachment($this->uuid(), (string) $second->id(), new DateTimeImmutable('2026-01-01T10:02:00+00:00'), 'c.pdf'));

    $found = $repository->findByMessageIds([(string) $first->id(), (string) $second->id()]);

    self::assertCount(3, $found);

    $messageIds = array_map(static fn (MessagingAttachment $a): string => $a->messageId(), $found);
    self::assertNotContains((string) $unrelated->id(), $messageIds);
  }

  #[Test]
  public function testFindByMessageIdsReturnsEmptyForNoMessageIds(): void
  {
    $repository = new MessagingAttachmentRepository($this->entityManager);

    self::assertSame([], $repository->findByMessageIds([]));
  }

  #[Test]
  public function testListByConversationIdPaginatesNewestUploadedFirst(): void
  {
    $messages = new MessagingMessageRepository($this->entityManager);
    $repository = new MessagingAttachmentRepository($this->entityManager);

    $message = $this->appendMessage($messages, 'Owns files');

    $oldId = $this->uuid();
    $midId = $this->uuid();
    $newId = $this->uuid();
    $repository->save($this->makeAttachment($oldId, (string) $message->id(), new DateTimeImmutable('2026-01-01T09:00:00+00:00'), 'old.pdf'));
    $repository->save($this->makeAttachment($midId, (string) $message->id(), new DateTimeImmutable('2026-01-01T10:00:00+00:00'), 'mid.pdf'));
    $repository->save($this->makeAttachment($newId, (string) $message->id(), new DateTimeImmutable('2026-01-01T11:00:00+00:00'), 'new.pdf'));

    $firstPage = $repository->listByConversationId(self::CONVERSATION_ID, 1, 2);

    self::assertSame(3, $firstPage->total);
    self::assertCount(2, $firstPage->items);
    self::assertSame($newId, (string) $firstPage->items[0]->id());
    self::assertSame($midId, (string) $firstPage->items[1]->id());

    $secondPage = $repository->listByConversationId(self::CONVERSATION_ID, 2, 2);

    self::assertCount(1, $secondPage->items);
    self::assertSame($oldId, (string) $secondPage->items[0]->id());
  }

  #[Test]
  public function testDeleteRemovesTheAttachmentAndIsANoOpWhenAbsent(): void
  {
    $messages = new MessagingMessageRepository($this->entityManager);
    $repository = new MessagingAttachmentRepository($this->entityManager);

    $message = $this->appendMessage($messages, 'Attachment to delete');
    $id = $this->uuid();
    $repository->save($this->makeAttachment($id, (string) $message->id(), new DateTimeImmutable('2026-01-01T10:00:00+00:00'), 'gone.pdf'));

    $repository->delete(MessagingAttachmentId::fromString($id));

    self::assertNull($repository->findById(MessagingAttachmentId::fromString($id)));

    // Deleting an already-absent record must not throw.
    $repository->delete(MessagingAttachmentId::fromString($this->uuid()));
  }

  private function makeAttachment(string $id, string $messageId, DateTimeImmutable $uploadedAt, string $fileName): MessagingAttachment
  {
    return MessagingAttachment::reconstitute(
      id: MessagingAttachmentId::fromString($id),
      messageId: $messageId,
      conversationId: self::CONVERSATION_ID,
      organizationId: self::ORG_ID,
      uploadedByMemberId: 'member-1',
      fileName: $fileName,
      storagePath: 'messaging/' . $id,
      mimeType: 'application/pdf',
      size: 1024,
      uploadedAt: $uploadedAt,
      label: null,
    );
  }

  private function appendMessage(MessagingMessageRepository $repository, string $body): Message
  {
    $message = Message::create(
      MessageId::fromString($this->uuid()),
      self::CONVERSATION_ID,
      self::ORG_ID,
      'author-1',
      $body,
      new MentionExtractor(),
    );

    $repository->append($message);

    return $message;
  }

  private function uuid(): string
  {
    return sprintf(
      '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
      mt_rand(0, 0xFFFF),
      mt_rand(0, 0xFFFF),
      mt_rand(0, 0xFFFF),
      mt_rand(0, 0x0FFF) | 0x4000,
      mt_rand(0, 0x3FFF) | 0x8000,
      mt_rand(0, 0xFFFF),
      mt_rand(0, 0xFFFF),
      mt_rand(0, 0xFFFF),
    );
  }

  private function createOrganization(string $id): OrganizationRecord
  {
    $organization = new OrganizationRecord();
    $organization->id = $id;
    $organization->name = 'Messaging Attachment Repository Test';
    $organization->slug = 'messaging-attachment-repository-test-' . $id;
    $organization->ownerUserId = '550e8400-e29b-41d4-a716-446655443900';
    $organization->createdByUserId = '550e8400-e29b-41d4-a716-446655443900';
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;
    $this->entityManager->persist($organization);

    return $organization;
  }

  private function createConversation(string $id, OrganizationRecord $organization): MessagingConversationRecord
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    $record = new MessagingConversationRecord();
    $record->id = $id;
    $record->organization = $organization;
    $record->subjectType = 'facility';
    $record->subjectId = '550e8400-e29b-41d4-a716-446655449001';
    $record->visibility = 'subject';
    $record->messagesCount = 0;
    $record->isArchived = false;
    $record->createdAt = $now;
    $record->updatedAt = $now;
    $this->entityManager->persist($record);

    return $record;
  }

  private function removeOrganization(string $id): void
  {
    $organization = $this->entityManager->find(OrganizationRecord::class, $id);
    if ($organization instanceof OrganizationRecord) {
      $this->entityManager->remove($organization);
      $this->entityManager->flush();
    }
  }
}
