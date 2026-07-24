<?php

declare(strict_types=1);

namespace Tests\Integration\Messaging\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Messaging\Domain\Model\Conversation\Conversation;
use Messaging\Domain\Service\DirectConversationKey;
use Messaging\Domain\ValueObject\{ConversationVisibility, MessagingSubjectType};
use Messaging\Infrastructure\Persistence\Doctrine\Record\MessagingConversationRecord;
use Messaging\Infrastructure\Persistence\Doctrine\Repository\MessagingConversationRepository;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Shared\Application\Factory\UuidFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_map;

/**
 * Test MessagingConversationRepositoryTest.
 *
 * Executes the REAL `getOrCreate()`/`list()` DBAL/DQL against the test
 * database — a mocked QueryBuilder would assert call shape without ever
 * parsing the query. Covers the two L2.4 (direct messages) regressions this
 * repository shipped bugs for:
 *
 * - `getOrCreate()` used to hardcode `visibility=SUBJECT` in its raw DBAL
 *   INSERT, which would have silently made every direct conversation
 *   SUBJECT-visible (defeating participant-based access control).
 * - `list()` used to filter only `subjectType != CHANNEL`, which would have
 *   leaked every direct conversation into `GET /api/conversations`.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MessagingConversationRepository::class)]
final class MessagingConversationRepositoryTest extends KernelTestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655447000';

  private EntityManagerInterface $entityManager;

  private MessagingConversationRepository $repository;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    /** @var UuidFactory $uuidFactory */
    $uuidFactory = static::getContainer()->get(UuidFactory::class);
    $this->repository = new MessagingConversationRepository($this->entityManager, $uuidFactory);

    $this->removeOrganization(self::ORG_ID);
    $this->createOrganization(self::ORG_ID);
    $this->entityManager->flush();
  }

  protected function tearDown(): void
  {
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testGetOrCreatePersistsTheGivenVisibilityInsteadOfHardcodingSubject(): void
  {
    $memberA = '550e8400-e29b-41d4-a716-446655447101';
    $memberB = '550e8400-e29b-41d4-a716-446655447102';
    $pairKey = DirectConversationKey::for($memberA, $memberB);

    $direct = $this->repository->getOrCreate(self::ORG_ID, MessagingSubjectType::DIRECT, $pairKey, ConversationVisibility::PARTICIPANTS);

    self::assertSame(ConversationVisibility::PARTICIPANTS->value, $direct->visibility, 'A direct conversation must be created PARTICIPANTS-visible, never the hardcoded SUBJECT default.');

    $subjectThread = $this->repository->getOrCreate(self::ORG_ID, MessagingSubjectType::FACILITY, 'facility-1', ConversationVisibility::SUBJECT);

    self::assertSame(ConversationVisibility::SUBJECT->value, $subjectThread->visibility);
  }

  #[Test]
  public function testGetOrCreateIsIdempotentAndReturnsTheSamePairRegardlessOfMemberOrder(): void
  {
    $memberA = '550e8400-e29b-41d4-a716-446655447201';
    $memberB = '550e8400-e29b-41d4-a716-446655447202';

    $keyForward = DirectConversationKey::for($memberA, $memberB);
    $keyBackward = DirectConversationKey::for($memberB, $memberA);

    $first = $this->repository->getOrCreate(self::ORG_ID, MessagingSubjectType::DIRECT, $keyForward, ConversationVisibility::PARTICIPANTS);
    $second = $this->repository->getOrCreate(self::ORG_ID, MessagingSubjectType::DIRECT, $keyBackward, ConversationVisibility::PARTICIPANTS);

    self::assertSame($first->id, $second->id, 'Both members deriving the pair key (in either order) must resolve to the SAME conversation.');
  }

  #[Test]
  public function testListExcludesBothChannelsAndDirectConversations(): void
  {
    $organization = $this->entityManager->find(OrganizationRecord::class, self::ORG_ID);
    self::assertInstanceOf(OrganizationRecord::class, $organization);

    $subjectThreadId = '550e8400-e29b-41d4-a716-446655447301';
    $channelId = '550e8400-e29b-41d4-a716-446655447302';
    $directId = '550e8400-e29b-41d4-a716-446655447303';

    $this->createConversation($subjectThreadId, $organization, 'facility', 'facility-1', 'subject');
    $this->createConversation($channelId, $organization, 'channel', null, 'participants', 'General');
    $this->createConversation($directId, $organization, 'direct', DirectConversationKey::for('member-a', 'member-b'), 'participants');
    $this->entityManager->flush();

    $page = $this->repository->list(self::ORG_ID, null, null, null, null, 1, 50);

    $ids = array_map(static fn ($view): string => $view->id, $page->items);

    self::assertContains($subjectThreadId, $ids);
    self::assertNotContains($channelId, $ids, 'A channel must never leak into GET /api/conversations.');
    self::assertNotContains($directId, $ids, 'A direct conversation must never leak into GET /api/conversations.');
  }

  /**
   * L2.6 — a mocked QueryBuilder never parses the DQL/ORM mapping, so this
   * executes the REAL `save()`/`findAggregateById()`/`findChannelById()`
   * round trip against the test database: setting a channel's parent,
   * persisting it, and reading it back through BOTH the aggregate path
   * (`Conversation::parentConversationId()`) and the read-model path
   * (`ChannelView::$parentChannelId`) — the two places a stale mapping
   * could silently regress.
   */
  #[Test]
  public function testSaveAndFindRoundTripAChannelsParentConversationId(): void
  {
    $organization = $this->entityManager->find(OrganizationRecord::class, self::ORG_ID);
    self::assertInstanceOf(OrganizationRecord::class, $organization);

    $parentId = '550e8400-e29b-41d4-a716-446655447401';
    $childId = '550e8400-e29b-41d4-a716-446655447402';

    $this->createConversation($parentId, $organization, 'channel', null, 'participants', 'Bâtiment Nord');
    $this->createConversation($childId, $organization, 'channel', null, 'participants', 'Extincteurs — RDC');
    $this->entityManager->flush();
    $this->entityManager->clear();

    $child = $this->repository->findAggregateById($childId);
    self::assertInstanceOf(Conversation::class, $child);
    self::assertNull($child->parentConversationId(), 'A freshly created channel must start top-level (no parent).');

    $child->setParent($parentId);
    $this->repository->save($child);
    $this->entityManager->clear();

    $reloaded = $this->repository->findAggregateById($childId);
    self::assertInstanceOf(Conversation::class, $reloaded);
    self::assertSame($parentId, $reloaded->parentConversationId(), 'The parent conversation id must survive a save/reload round trip.');

    $channelView = $this->repository->findChannelById($childId);
    self::assertNotNull($channelView);
    self::assertSame($parentId, $channelView->parentChannelId, 'ChannelView must expose the parent channel id for the client-side tree.');

    // Clearing the parent must persist as NULL, not merely detach in memory.
    $reloaded->setParent(null);
    $this->repository->save($reloaded);
    $this->entityManager->clear();

    $detached = $this->repository->findAggregateById($childId);
    self::assertInstanceOf(Conversation::class, $detached);
    self::assertNull($detached->parentConversationId(), 'Clearing the parent must persist as NULL, making the channel top-level again.');
  }

  /**
   * `listChannelsForMember()` (the `GET /api/channels` list) must expose the
   * SAME `parentChannelId` per row as `findChannelById()`, without an extra
   * query per row — this is what lets a client group the flat page into a
   * tree.
   */
  #[Test]
  public function testListChannelsForMemberExposesTheParentChannelIdWithoutNPlusOne(): void
  {
    $organization = $this->entityManager->find(OrganizationRecord::class, self::ORG_ID);
    self::assertInstanceOf(OrganizationRecord::class, $organization);

    $parentId = '550e8400-e29b-41d4-a716-446655447501';
    $childId = '550e8400-e29b-41d4-a716-446655447502';
    $memberId = '550e8400-e29b-41d4-a716-446655447503';

    $parentRecord = $this->createConversation($parentId, $organization, 'channel', null, 'participants', 'Bâtiment Nord');
    $childRecord = $this->createConversation($childId, $organization, 'channel', null, 'participants', 'Extincteurs — RDC');
    $childRecord->parentConversation = $parentRecord;
    $this->entityManager->flush();
    $this->entityManager->clear();

    $this->addParticipant($childId, $organization, $memberId);
    $this->addParticipant($parentId, $organization, $memberId);
    $this->entityManager->flush();

    $page = $this->repository->listChannelsForMember(self::ORG_ID, $memberId, null, 1, 50);

    $parentChannelIdsById = [];
    foreach ($page->items as $item) {
      $parentChannelIdsById[$item->id] = $item->parentChannelId;
    }

    self::assertArrayHasKey($childId, $parentChannelIdsById);
    self::assertSame($parentId, $parentChannelIdsById[$childId]);
    self::assertArrayHasKey($parentId, $parentChannelIdsById);
    self::assertNull($parentChannelIdsById[$parentId]);
  }

  private function addParticipant(string $conversationId, OrganizationRecord $organization, string $memberId): void
  {
    $this->entityManager->getConnection()->executeStatement(
      'INSERT INTO messaging_participants (conversation_id, organization_id, member_id, source, added_at) VALUES (:conversationId, :organizationId, :memberId, :source, :addedAt)',
      [
        'conversationId' => $conversationId,
        'organizationId' => $organization->id,
        'memberId' => $memberId,
        'source' => 'manual',
        'addedAt' => new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      ],
      ['addedAt' => 'datetime_immutable'],
    );
  }

  private function createOrganization(string $id): OrganizationRecord
  {
    $organization = new OrganizationRecord();
    $organization->id = $id;
    $organization->name = 'Messaging Conversation Repository Test';
    $organization->slug = 'messaging-conversation-repository-test-' . $id;
    $organization->ownerUserId = '550e8400-e29b-41d4-a716-446655443900';
    $organization->createdByUserId = '550e8400-e29b-41d4-a716-446655443900';
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;
    $this->entityManager->persist($organization);

    return $organization;
  }

  private function createConversation(
    string $id,
    OrganizationRecord $organization,
    string $subjectType,
    ?string $subjectId,
    string $visibility,
    ?string $name = null,
  ): MessagingConversationRecord {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    $record = new MessagingConversationRecord();
    $record->id = $id;
    $record->organization = $organization;
    $record->subjectType = $subjectType;
    $record->subjectId = $subjectId;
    $record->visibility = $visibility;
    $record->name = $name;
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
