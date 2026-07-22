<?php

declare(strict_types=1);

namespace Tests\Integration\Messaging\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Messaging\Domain\Model\Message\Message;
use Messaging\Domain\Service\MentionExtractor;
use Messaging\Domain\ValueObject\MessageId;
use Messaging\Infrastructure\Persistence\Doctrine\Record\{MessagingConversationRecord, MessagingMessageRecord};
use Messaging\Infrastructure\Persistence\Doctrine\Repository\{MessagingLinkRepository, MessagingMessageRepository};
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Shared\Application\Factory\UuidFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_column;
use function mt_rand;
use function sprintf;

/**
 * Test MessagingLinkRepositoryTest.
 *
 * Executes the REAL `replaceForMessage()`/`listByConversation()` DBAL/DQL
 * against the test database (B2, conversation links) — a mocked
 * QueryBuilder would never catch a broken conversation scope or a stale
 * `DELETE ... WHERE message_id` predicate.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MessagingLinkRepository::class)]
#[CoversClass(MessagingMessageRepository::class)]
final class MessagingLinkRepositoryTest extends KernelTestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655447000';

  private const string CONVERSATION_ID = '550e8400-e29b-41d4-a716-446655447010';

  private const string OTHER_CONVERSATION_ID = '550e8400-e29b-41d4-a716-446655447011';

  private EntityManagerInterface $entityManager;

  private MessagingLinkRepository $links;

  private MessagingMessageRepository $messages;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;
    /** @var UuidFactory $uuidFactory */
    $uuidFactory = static::getContainer()->get(UuidFactory::class);

    $this->links = new MessagingLinkRepository($this->entityManager, $uuidFactory);
    $this->messages = new MessagingMessageRepository($this->entityManager);

    $this->removeOrganization(self::ORG_ID);

    $organization = $this->createOrganization(self::ORG_ID);
    $this->createConversation(self::CONVERSATION_ID, $organization, '550e8400-e29b-41d4-a716-446655449201');
    $this->createConversation(self::OTHER_CONVERSATION_ID, $organization, '550e8400-e29b-41d4-a716-446655449202');
    $this->entityManager->flush();
  }

  protected function tearDown(): void
  {
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testReplaceForMessagePersistsEveryUrl(): void
  {
    $message = $this->appendMessage(self::CONVERSATION_ID, 'See https://example.com/a and https://example.com/b');

    $now = new DateTimeImmutable('2026-07-01T00:00:00+00:00');
    $this->links->replaceForMessage((string) $message->id(), self::CONVERSATION_ID, ['https://example.com/a', 'https://example.com/b'], $now);

    $page = $this->links->listByConversation(self::CONVERSATION_ID, 1, 30);

    self::assertSame(2, $page->total);
  }

  #[Test]
  public function testReplaceForMessageWithAnEmptyListClearsExistingLinks(): void
  {
    $message = $this->appendMessage(self::CONVERSATION_ID, 'See https://example.com/a');

    $now = new DateTimeImmutable('2026-07-01T00:00:00+00:00');
    $this->links->replaceForMessage((string) $message->id(), self::CONVERSATION_ID, ['https://example.com/a'], $now);
    self::assertSame(1, $this->links->listByConversation(self::CONVERSATION_ID, 1, 30)->total);

    $this->links->replaceForMessage((string) $message->id(), self::CONVERSATION_ID, [], $now);

    self::assertSame(0, $this->links->listByConversation(self::CONVERSATION_ID, 1, 30)->total);
  }

  #[Test]
  public function testReplaceForMessageOnEditReplacesThePriorExtractionWholesale(): void
  {
    $message = $this->appendMessage(self::CONVERSATION_ID, 'See https://example.com/old');

    $now = new DateTimeImmutable('2026-07-01T00:00:00+00:00');
    $this->links->replaceForMessage((string) $message->id(), self::CONVERSATION_ID, ['https://example.com/old'], $now);

    $this->links->replaceForMessage((string) $message->id(), self::CONVERSATION_ID, ['https://example.com/new'], $now);

    $page = $this->links->listByConversation(self::CONVERSATION_ID, 1, 30);

    self::assertSame(1, $page->total);
    self::assertSame('https://example.com/new', $page->items[0]->url);
  }

  #[Test]
  public function testListByConversationScopesToTheConversationAndOrdersNewestFirst(): void
  {
    $messageA = $this->appendMessage(self::CONVERSATION_ID, 'https://example.com/first');
    $messageB = $this->appendMessage(self::CONVERSATION_ID, 'https://example.com/second');
    $messageElsewhere = $this->appendMessage(self::OTHER_CONVERSATION_ID, 'https://example.com/elsewhere');

    $this->links->replaceForMessage((string) $messageA->id(), self::CONVERSATION_ID, ['https://example.com/first'], new DateTimeImmutable('2026-07-01T00:00:01+00:00'));
    $this->links->replaceForMessage((string) $messageB->id(), self::CONVERSATION_ID, ['https://example.com/second'], new DateTimeImmutable('2026-07-01T00:00:02+00:00'));
    $this->links->replaceForMessage((string) $messageElsewhere->id(), self::OTHER_CONVERSATION_ID, ['https://example.com/elsewhere'], new DateTimeImmutable('2026-07-01T00:00:03+00:00'));

    $page = $this->links->listByConversation(self::CONVERSATION_ID, 1, 30);

    self::assertSame(2, $page->total);
    self::assertSame('https://example.com/second', $page->items[0]->url, 'Newest first.');
    self::assertSame('https://example.com/first', $page->items[1]->url);
  }

  #[Test]
  public function testLinkBackfillBatchUsesAnExclusiveDeterministicCursor(): void
  {
    $firstId = '10000000-0000-4000-8000-000000000001';
    $secondId = '10000000-0000-4000-8000-000000000002';
    $thirdId = '10000000-0000-4000-8000-000000000003';
    $this->appendMessage(self::CONVERSATION_ID, 'first', $firstId);
    $this->appendMessage(self::CONVERSATION_ID, 'second', $secondId);
    $this->appendMessage(self::OTHER_CONVERSATION_ID, 'third', $thirdId);

    $deleted = $this->entityManager->find(MessagingMessageRecord::class, $secondId);
    self::assertInstanceOf(MessagingMessageRecord::class, $deleted);
    $deleted->deletedAt = new DateTimeImmutable('2026-07-21T12:00:00+00:00');
    $this->entityManager->flush();

    $firstBatch = $this->messages->listLinkBackfillBatch(null, 2);

    self::assertSame([$firstId, $secondId], array_column($firstBatch, 'messageId'));
    self::assertFalse($firstBatch[0]->isDeleted);
    self::assertTrue($firstBatch[1]->isDeleted);

    $secondBatch = $this->messages->listLinkBackfillBatch($secondId, 2);

    self::assertCount(1, $secondBatch);
    self::assertSame($thirdId, $secondBatch[0]->messageId);
  }

  private function appendMessage(string $conversationId, string $body, ?string $id = null): Message
  {
    $message = Message::create(
      MessageId::fromString($id ?? $this->uuid()),
      $conversationId,
      self::ORG_ID,
      'author-1',
      $body,
      new MentionExtractor(),
    );

    $this->messages->append($message);

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
    $organization->name = 'Messaging Link Repository Test';
    $organization->slug = 'messaging-link-repository-test-' . $id;
    $organization->ownerUserId = '550e8400-e29b-41d4-a716-446655443900';
    $organization->createdByUserId = '550e8400-e29b-41d4-a716-446655443900';
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;
    $this->entityManager->persist($organization);

    return $organization;
  }

  private function createConversation(string $id, OrganizationRecord $organization, string $subjectId): MessagingConversationRecord
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    $record = new MessagingConversationRecord();
    $record->id = $id;
    $record->organization = $organization;
    $record->subjectType = 'facility';
    $record->subjectId = $subjectId;
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
