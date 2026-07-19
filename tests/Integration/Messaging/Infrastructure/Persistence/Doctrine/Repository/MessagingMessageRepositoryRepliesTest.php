<?php

declare(strict_types=1);

namespace Tests\Integration\Messaging\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Messaging\Application\Contract\Message\MessageView;
use Messaging\Domain\Model\Message\Message;
use Messaging\Domain\Service\MentionExtractor;
use Messaging\Domain\ValueObject\MessageId;
use Messaging\Infrastructure\Persistence\Doctrine\Record\MessagingConversationRecord;
use Messaging\Infrastructure\Persistence\Doctrine\Repository\MessagingMessageRepository;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_map;
use function mt_rand;
use function sprintf;

/**
 * Test MessagingMessageRepositoryRepliesTest.
 *
 * Executes the REAL `listByConversation()`/`listRepliesByParent()`/
 * `incrementReplyCount()` DQL/DBAL against the test database — a mocked
 * QueryBuilder would assert call shape without ever parsing the DQL, and
 * would not catch a broken filter/scope (the exact way the `MEMBER OF`
 * reserved-keyword bug shipped past 418 green unit tests in an earlier
 * lot). Also pins the "provable no-op on legacy rows" claim documented in
 * `MessagingMessageRepositoryPort::listByConversation()`: on a conversation
 * with only root messages (`parent_message_id = NULL` for every row, the
 * state of every pre-L2.5 conversation), the new
 * `AND m.parentMessage IS NULL` filter changes nothing about the returned
 * offsets, ordering or total.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MessagingMessageRepository::class)]
final class MessagingMessageRepositoryRepliesTest extends KernelTestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655446000';

  private const string CONVERSATION_ID = '550e8400-e29b-41d4-a716-446655446010';

  private const string OTHER_CONVERSATION_ID = '550e8400-e29b-41d4-a716-446655446011';

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
  public function testListByConversationIsANoOpOnLegacyRootOnlyData(): void
  {
    $repository = new MessagingMessageRepository($this->entityManager);

    $first = $this->appendRoot($repository, self::CONVERSATION_ID, 'First root message');
    $second = $this->appendRoot($repository, self::CONVERSATION_ID, 'Second root message');

    $page = $repository->listByConversation(self::CONVERSATION_ID, 1, 30);

    self::assertSame(2, $page->total);
    self::assertCount(2, $page->items);
    self::assertSame((string) $first->id(), $page->items[0]->id, 'Oldest first, unchanged.');
    self::assertSame((string) $second->id(), $page->items[1]->id);
  }

  #[Test]
  public function testListByConversationExcludesThreadedReplies(): void
  {
    $repository = new MessagingMessageRepository($this->entityManager);

    $root = $this->appendRoot($repository, self::CONVERSATION_ID, 'Root message');
    $this->appendReply($repository, self::CONVERSATION_ID, $root, 'A reply');
    $this->appendReply($repository, self::CONVERSATION_ID, $root, 'Another reply');

    $page = $repository->listByConversation(self::CONVERSATION_ID, 1, 30);

    self::assertSame(1, $page->total, 'Replies must never surface in the root list.');
    self::assertSame((string) $root->id(), $page->items[0]->id);
  }

  #[Test]
  public function testListRepliesByParentReturnsOnlyThatParentsRepliesOldestFirst(): void
  {
    $repository = new MessagingMessageRepository($this->entityManager);

    $root = $this->appendRoot($repository, self::CONVERSATION_ID, 'Root message');
    $otherRoot = $this->appendRoot($repository, self::OTHER_CONVERSATION_ID, 'Unrelated root message');

    $firstReply = $this->appendReply($repository, self::CONVERSATION_ID, $root, 'First reply');
    $secondReply = $this->appendReply($repository, self::CONVERSATION_ID, $root, 'Second reply');
    $unrelatedReply = $this->appendReply($repository, self::OTHER_CONVERSATION_ID, $otherRoot, 'Reply to a different parent');

    $page = $repository->listRepliesByParent((string) $root->id(), 1, 30);

    self::assertSame(2, $page->total);
    self::assertSame((string) $firstReply->id(), $page->items[0]->id, 'Oldest reply first.');
    self::assertSame((string) $secondReply->id(), $page->items[1]->id);

    $ids = array_map(static fn (MessageView $item): string => $item->id, $page->items);
    self::assertNotContains((string) $unrelatedReply->id(), $ids, 'A reply to a DIFFERENT parent must never leak into this parent\'s thread.');
  }

  #[Test]
  public function testIncrementReplyCountAtomicallyBumpsTheParentsCounter(): void
  {
    $repository = new MessagingMessageRepository($this->entityManager);

    $root = $this->appendRoot($repository, self::CONVERSATION_ID, 'Root message');
    self::assertSame(0, $root->replyCount());

    $repository->incrementReplyCount((string) $root->id());
    $repository->incrementReplyCount((string) $root->id());

    // The raw DBAL UPDATE bypasses the ORM identity map, so the
    // already-loaded `$root` record must be detached before re-fetching —
    // otherwise `find()` would return the stale cached instance instead of
    // issuing a fresh SELECT.
    $this->entityManager->clear();

    $reloaded = $repository->findById((string) $root->id());
    self::assertNotNull($reloaded);
    self::assertSame(2, $reloaded->replyCount);
  }

  private function appendRoot(MessagingMessageRepository $repository, string $conversationId, string $body): Message
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

  private function appendReply(MessagingMessageRepository $repository, string $conversationId, Message $parent, string $body): Message
  {
    $reply = Message::create(
      MessageId::fromString($this->uuid()),
      $conversationId,
      self::ORG_ID,
      'author-1',
      $body,
      new MentionExtractor(),
      (string) $parent->id(),
    );

    $repository->append($reply);

    return $reply;
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
    $organization->name = 'Messaging Message Repository Replies Test';
    $organization->slug = 'messaging-message-repository-replies-test-' . $id;
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
