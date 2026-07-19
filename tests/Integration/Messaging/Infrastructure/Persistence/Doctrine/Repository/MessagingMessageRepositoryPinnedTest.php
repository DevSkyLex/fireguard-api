<?php

declare(strict_types=1);

namespace Tests\Integration\Messaging\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Messaging\Application\Contract\Message\MessageView;
use Messaging\Domain\Model\Message\Message;
use Messaging\Domain\Service\MentionExtractor;
use Messaging\Domain\ValueObject\MessageId;
use Messaging\Infrastructure\Persistence\Doctrine\Record\{MessagingConversationRecord, MessagingMessageRecord};
use Messaging\Infrastructure\Persistence\Doctrine\Repository\MessagingMessageRepository;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_map;
use function mt_rand;
use function sprintf;

/**
 * Test MessagingMessageRepositoryPinnedTest.
 *
 * Executes the REAL `listPinnedByConversation()` DQL against the test
 * database — a mocked QueryBuilder would assert call shape without ever
 * parsing the DQL, and would not catch a broken filter/scope.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MessagingMessageRepository::class)]
final class MessagingMessageRepositoryPinnedTest extends KernelTestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655445000';

  private const string CONVERSATION_ID = '550e8400-e29b-41d4-a716-446655445010';

  private const string OTHER_CONVERSATION_ID = '550e8400-e29b-41d4-a716-446655445011';

  private EntityManagerInterface $entityManager;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->removeOrganization(self::ORG_ID);

    $organization = $this->createOrganization(self::ORG_ID);
    $this->createConversation(self::CONVERSATION_ID, $organization, '550e8400-e29b-41d4-a716-446655449001');
    $this->createConversation(self::OTHER_CONVERSATION_ID, $organization, '550e8400-e29b-41d4-a716-446655449002');
    $this->entityManager->flush();
  }

  protected function tearDown(): void
  {
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testListPinnedByConversationOnlyReturnsPinnedMessagesScopedToTheConversation(): void
  {
    $repository = new MessagingMessageRepository($this->entityManager);

    $pinned = $this->appendMessage($repository, self::CONVERSATION_ID, 'Pinned in this conversation');
    $unpinned = $this->appendMessage($repository, self::CONVERSATION_ID, 'Never pinned');
    $pinnedElsewhere = $this->appendMessage($repository, self::OTHER_CONVERSATION_ID, 'Pinned in another conversation');

    $this->pin($repository, $pinned, 'member-1');
    $this->pin($repository, $pinnedElsewhere, 'member-1');

    $page = $repository->listPinnedByConversation(self::CONVERSATION_ID, 1, 30);

    self::assertSame(1, $page->total);
    self::assertCount(1, $page->items);
    self::assertSame('Pinned in this conversation', $page->items[0]->body);
    self::assertNotNull($page->items[0]->pinnedAt);
    self::assertSame('member-1', $page->items[0]->pinnedByMemberId);

    // Sanity: the unpinned message and the message pinned in the OTHER
    // conversation must never leak into this conversation's pinned page.
    $ids = array_map(static fn (MessageView $item): string => $item->id, $page->items);
    self::assertNotContains((string) $unpinned->id(), $ids);
    self::assertNotContains((string) $pinnedElsewhere->id(), $ids);
  }

  #[Test]
  public function testListPinnedByConversationOrdersMostRecentlyPinnedFirst(): void
  {
    $repository = new MessagingMessageRepository($this->entityManager);

    $first = $this->appendMessage($repository, self::CONVERSATION_ID, 'Pinned first');
    $second = $this->appendMessage($repository, self::CONVERSATION_ID, 'Pinned second');

    // Pin with explicit, one-second-apart timestamps (rather than relying on
    // the domain clock, which can tie within the same DB-column precision)
    // so the ordering assertion below is deterministic.
    $this->pinAt($first, 'member-1', new DateTimeImmutable('2026-01-01T00:00:01+00:00'));
    $this->pinAt($second, 'member-1', new DateTimeImmutable('2026-01-01T00:00:02+00:00'));

    $page = $repository->listPinnedByConversation(self::CONVERSATION_ID, 1, 30);

    self::assertSame(2, $page->total);
    self::assertSame('Pinned second', $page->items[0]->body, 'Most recently pinned must come first.');
    self::assertSame('Pinned first', $page->items[1]->body);
  }

  #[Test]
  public function testUnpinRemovesTheMessageFromThePinnedPage(): void
  {
    $repository = new MessagingMessageRepository($this->entityManager);

    $message = $this->appendMessage($repository, self::CONVERSATION_ID, 'Pin then unpin');
    $this->pin($repository, $message, 'member-1');

    self::assertSame(1, $repository->listPinnedByConversation(self::CONVERSATION_ID, 1, 30)->total);

    $aggregate = $repository->findAggregateById((string) $message->id());
    self::assertNotNull($aggregate);
    $aggregate->unpin();
    $repository->save($aggregate);

    self::assertSame(0, $repository->listPinnedByConversation(self::CONVERSATION_ID, 1, 30)->total);
  }

  private function appendMessage(MessagingMessageRepository $repository, string $conversationId, string $body): Message
  {
    $message = Message::create(
      MessageId::fromString($this->uuid()),
      $conversationId,
      self::ORG_ID,
      'author-1',
      $body,
      new MentionExtractor(),
    );

    $repository->append($message);

    return $message;
  }

  private function pin(MessagingMessageRepository $repository, Message $message, string $pinnedByMemberId): void
  {
    $aggregate = $repository->findAggregateById((string) $message->id());
    self::assertNotNull($aggregate);
    $aggregate->pin($pinnedByMemberId);
    $repository->save($aggregate);
  }

  private function pinAt(Message $message, string $pinnedByMemberId, DateTimeImmutable $pinnedAt): void
  {
    $record = $this->entityManager->find(MessagingMessageRecord::class, (string) $message->id());
    self::assertInstanceOf(MessagingMessageRecord::class, $record);
    $record->pinnedAt = $pinnedAt;
    $record->pinnedByMemberId = $pinnedByMemberId;
    $this->entityManager->flush();
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
    $organization->name = 'Messaging Message Repository Pinned Test';
    $organization->slug = 'messaging-message-repository-pinned-test-' . $id;
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
