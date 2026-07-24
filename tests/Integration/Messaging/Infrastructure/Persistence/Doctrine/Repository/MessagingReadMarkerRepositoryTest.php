<?php

declare(strict_types=1);

namespace Tests\Integration\Messaging\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Messaging\Domain\Model\Message\Message;
use Messaging\Domain\Service\MentionExtractor;
use Messaging\Domain\ValueObject\MessageId;
use Messaging\Infrastructure\Persistence\Doctrine\Record\MessagingConversationRecord;
use Messaging\Infrastructure\Persistence\Doctrine\Repository\{MessagingMessageRepository, MessagingReadMarkerRepository};
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function mt_rand;
use function sprintf;

/**
 * Test MessagingReadMarkerRepository.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MessagingReadMarkerRepository::class)]
final class MessagingReadMarkerRepositoryTest extends KernelTestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655448000';

  private const string CONVERSATION_ID = '550e8400-e29b-41d4-a716-446655448010';

  private const string OTHER_CONVERSATION_ID = '550e8400-e29b-41d4-a716-446655448011';

  private const string READER_ID = 'reader-1';

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
  public function testUnreadCountsWithoutAMarkerCountsOtherMembersMessagesOnly(): void
  {
    $messages = new MessagingMessageRepository($this->entityManager);
    $repository = new MessagingReadMarkerRepository($this->entityManager);

    $this->appendMessage($messages, 'author-other', 'From someone else');
    $this->appendMessage($messages, self::READER_ID, 'From the reader themselves');

    $counts = $repository->unreadCounts(self::ORG_ID, self::READER_ID, [self::CONVERSATION_ID]);

    self::assertSame([self::CONVERSATION_ID => 1], $counts);
  }

  #[Test]
  public function testUnreadCountsIsZeroWhenTheMarkerIsAfterEveryMessage(): void
  {
    $messages = new MessagingMessageRepository($this->entityManager);
    $repository = new MessagingReadMarkerRepository($this->entityManager);

    $this->appendMessage($messages, 'author-other', 'Already read');

    $repository->upsert(self::CONVERSATION_ID, self::ORG_ID, self::READER_ID, new DateTimeImmutable('2100-01-01T00:00:00+00:00'), null);

    $counts = $repository->unreadCounts(self::ORG_ID, self::READER_ID, [self::CONVERSATION_ID]);

    self::assertSame([self::CONVERSATION_ID => 0], $counts);
  }

  #[Test]
  public function testUnreadCountsReturnsZeroFilledMapForEmptyConversationList(): void
  {
    $repository = new MessagingReadMarkerRepository($this->entityManager);

    self::assertSame([], $repository->unreadCounts(self::ORG_ID, self::READER_ID, []));
  }

  #[Test]
  public function testUpsertUpdatesTheExistingMarkerInPlace(): void
  {
    $repository = new MessagingReadMarkerRepository($this->entityManager);

    $repository->upsert(self::CONVERSATION_ID, self::ORG_ID, self::READER_ID, new DateTimeImmutable('2026-01-01T08:00:00+00:00'), null);
    $repository->upsert(self::CONVERSATION_ID, self::ORG_ID, self::READER_ID, new DateTimeImmutable('2026-01-02T09:30:00+00:00'), 'last-message-id');

    $lastReadAt = $repository->lastReadAtByConversations(self::READER_ID, [self::CONVERSATION_ID]);

    self::assertArrayHasKey(self::CONVERSATION_ID, $lastReadAt);
    self::assertSame('2026-01-02T09:30:00+00:00', $lastReadAt[self::CONVERSATION_ID]->format('c'));
  }

  #[Test]
  public function testLastReadAtByConversationsOmitsConversationsWithNoMarker(): void
  {
    $repository = new MessagingReadMarkerRepository($this->entityManager);

    $repository->upsert(self::CONVERSATION_ID, self::ORG_ID, self::READER_ID, new DateTimeImmutable('2026-01-01T08:00:00+00:00'), null);

    $lastReadAt = $repository->lastReadAtByConversations(self::READER_ID, [self::CONVERSATION_ID, self::OTHER_CONVERSATION_ID]);

    self::assertArrayHasKey(self::CONVERSATION_ID, $lastReadAt);
    self::assertArrayNotHasKey(self::OTHER_CONVERSATION_ID, $lastReadAt);
  }

  #[Test]
  public function testLastReadAtByConversationsReturnsEmptyForEmptyConversationList(): void
  {
    $repository = new MessagingReadMarkerRepository($this->entityManager);

    self::assertSame([], $repository->lastReadAtByConversations(self::READER_ID, []));
  }

  private function appendMessage(MessagingMessageRepository $repository, string $authorMemberId, string $body): void
  {
    $message = Message::create(
      MessageId::fromString($this->uuid()),
      self::CONVERSATION_ID,
      self::ORG_ID,
      $authorMemberId,
      $body,
      new MentionExtractor(),
    );

    $repository->append($message);
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
    $organization->name = 'Messaging Read Marker Repository Test';
    $organization->slug = 'messaging-read-marker-repository-test-' . $id;
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
