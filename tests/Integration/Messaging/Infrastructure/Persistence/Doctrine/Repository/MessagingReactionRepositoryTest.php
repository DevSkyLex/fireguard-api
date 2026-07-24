<?php

declare(strict_types=1);

namespace Tests\Integration\Messaging\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Messaging\Application\Contract\Reaction\MessageReactionView;
use Messaging\Domain\Model\Message\Message;
use Messaging\Domain\Service\MentionExtractor;
use Messaging\Domain\ValueObject\MessageId;
use Messaging\Infrastructure\Persistence\Doctrine\Record\MessagingConversationRecord;
use Messaging\Infrastructure\Persistence\Doctrine\Repository\{MessagingMessageRepository, MessagingReactionRepository};
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_map;
use function mt_rand;
use function sprintf;

/**
 * Test MessagingReactionRepositoryTest.
 *
 * Executes the REAL `findByMessageIds()` DQL (an `IDENTITY(r.message)`
 * scalar select, mirroring `MessagingReadMarkerRepository::unreadCounts()`)
 * against the test database, plus `add()`/`remove()` — a mocked
 * QueryBuilder would assert call shape without ever parsing the DQL, and
 * would not catch a reserved-keyword alias or a broken IN-clause parameter.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MessagingReactionRepository::class)]
final class MessagingReactionRepositoryTest extends KernelTestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655446000';

  private const string CONVERSATION_ID = '550e8400-e29b-41d4-a716-446655446010';

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
  public function testAddIsIdempotentWhenReactingTwiceWithTheSameEmoji(): void
  {
    $messages = new MessagingMessageRepository($this->entityManager);
    $reactions = new MessagingReactionRepository($this->entityManager);

    $message = $this->appendMessage($messages, 'React to me');
    $now = new DateTimeImmutable('2026-01-01T00:00:01+00:00');

    $reactions->add((string) $message->id(), self::ORG_ID, 'member-1', "\u{1F44D}", $now);
    $reactions->add((string) $message->id(), self::ORG_ID, 'member-1', "\u{1F44D}", $now);

    $views = $reactions->findByMessageIds([(string) $message->id()]);

    self::assertCount(1, $views, 'Reacting twice with the same emoji must be a silent no-op, not a duplicate row.');
  }

  #[Test]
  public function testRemoveIsIdempotentOnANeverReactedRow(): void
  {
    $messages = new MessagingMessageRepository($this->entityManager);
    $reactions = new MessagingReactionRepository($this->entityManager);

    $message = $this->appendMessage($messages, 'Never reacted to');

    // Must not throw, even though nothing exists to remove.
    $reactions->remove((string) $message->id(), 'member-1', "\u{1F44D}");

    self::assertSame([], $reactions->findByMessageIds([(string) $message->id()]));
  }

  #[Test]
  public function testFindByMessageIdsBatchesReactionsAcrossSeveralMessagesInOneQuery(): void
  {
    $messages = new MessagingMessageRepository($this->entityManager);
    $reactions = new MessagingReactionRepository($this->entityManager);

    $first = $this->appendMessage($messages, 'First message');
    $second = $this->appendMessage($messages, 'Second message');
    $unrelated = $this->appendMessage($messages, 'Never reacted to');

    $now = new DateTimeImmutable('2026-01-01T00:00:01+00:00');
    $reactions->add((string) $first->id(), self::ORG_ID, 'member-1', "\u{1F44D}", $now);
    $reactions->add((string) $first->id(), self::ORG_ID, 'member-2', "\u{1F44D}", $now);
    $reactions->add((string) $second->id(), self::ORG_ID, 'member-1', "\u{1F389}", $now);

    $views = $reactions->findByMessageIds([(string) $first->id(), (string) $second->id()]);

    self::assertCount(3, $views);

    $messageIds = array_map(static fn (MessageReactionView $view): string => $view->messageId, $views);
    self::assertNotContains((string) $unrelated->id(), $messageIds);
  }

  #[Test]
  public function testRemoveOnlyDeletesTheActingMembersOwnReaction(): void
  {
    $messages = new MessagingMessageRepository($this->entityManager);
    $reactions = new MessagingReactionRepository($this->entityManager);

    $message = $this->appendMessage($messages, 'Shared reaction target');
    $now = new DateTimeImmutable('2026-01-01T00:00:01+00:00');

    $reactions->add((string) $message->id(), self::ORG_ID, 'member-1', "\u{1F44D}", $now);
    $reactions->add((string) $message->id(), self::ORG_ID, 'member-2', "\u{1F44D}", $now);

    $reactions->remove((string) $message->id(), 'member-1', "\u{1F44D}");

    $views = $reactions->findByMessageIds([(string) $message->id()]);

    self::assertCount(1, $views);
    self::assertSame('member-2', $views[0]->memberId);
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
    $organization->name = 'Messaging Reaction Repository Test';
    $organization->slug = 'messaging-reaction-repository-test-' . $id;
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
