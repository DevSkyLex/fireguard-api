<?php

declare(strict_types=1);

namespace Tests\Integration\Messaging\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Messaging\Application\Contract\Channel\ChannelView;
use Messaging\Application\Contract\Conversation\ConversationView;
use Messaging\Domain\Exception\MessagingNotFoundException;
use Messaging\Domain\Model\Conversation\Conversation;
use Messaging\Domain\ValueObject\{ChannelName, ConversationId, ConversationVisibility, MessagingSubjectType};
use Messaging\Infrastructure\Persistence\Doctrine\Record\MessagingConversationRecord;
use Messaging\Infrastructure\Persistence\Doctrine\Repository\MessagingConversationRepository;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_map;
use function sprintf;

/**
 * Test MessagingConversationRepositoryListingTest.
 *
 * Complements {@see MessagingConversationRepositoryTest} (which pins the two
 * L2.4 visibility/leak regressions) by exercising the remaining public
 * surface of the repository against the REAL test database: `findById()`,
 * `findAggregateById()` (found + not-found), `createChannel()`,
 * `findChannelById()` (view/not-a-channel/missing), `touchOnNewMessage()`,
 * every `list()` filter and its pagination clamps, the unread-marker filter,
 * `listChannelsForMember()`/`listDirectConversationsForMember()` scoping,
 * `findChannelIdsBoundToTeam()`, `findSubjectTypesByIds()`, and the
 * not-found `save()` guard. A mocked QueryBuilder would assert call shape
 * without ever parsing the DQL/ON CONFLICT SQL, so this boots the kernel and
 * runs the queries for real.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MessagingConversationRepository::class)]
final class MessagingConversationRepositoryListingTest extends KernelTestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655448000';

  private const string OWNER_ID = '550e8400-e29b-41d4-a716-446655448900';

  private EntityManagerInterface $entityManager;

  private MessagingConversationRepository $repository;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    /** @var MessagingConversationRepository $repository */
    $repository = static::getContainer()->get(MessagingConversationRepository::class);
    $this->repository = $repository;

    $this->createOrganization();
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
  public function testFindByIdReturnsViewAndNullForMissing(): void
  {
    $id = $this->conversationId(1);
    $this->createConversation($id, 'facility', 'facility-find', 'subject');
    $this->entityManager->flush();
    $this->entityManager->clear();

    $view = $this->repository->findById($id);
    self::assertNotNull($view);
    self::assertSame($id, $view->id);
    self::assertSame(self::ORG_ID, $view->organizationId);
    self::assertSame('facility', $view->subjectType);
    self::assertSame('facility-find', $view->subjectId);
    self::assertSame('subject', $view->visibility);

    self::assertNull($this->repository->findById($this->conversationId(98)));
  }

  #[Test]
  public function testFindAggregateByIdRebuildsChannelAggregateAndNullForMissing(): void
  {
    self::assertNull($this->repository->findAggregateById($this->conversationId(98)));

    $id = $this->conversationId(2);
    $this->createConversation($id, 'channel', null, 'participants', 'Aggregat');
    $this->entityManager->flush();
    $this->entityManager->clear();

    $aggregate = $this->repository->findAggregateById($id);
    self::assertInstanceOf(Conversation::class, $aggregate);
    self::assertSame(MessagingSubjectType::CHANNEL, $aggregate->subjectType());
    self::assertSame(ConversationVisibility::PARTICIPANTS, $aggregate->visibility());
    self::assertNotNull($aggregate->name());
    self::assertSame('Aggregat', (string) $aggregate->name());
    self::assertNull($aggregate->parentConversationId());
  }

  #[Test]
  public function testCreateChannelPersistsAndFindChannelByIdResolvesCounts(): void
  {
    $channelId = $this->conversationId(3);
    $channel = Conversation::createChannel(
      ConversationId::fromString($channelId),
      self::ORG_ID,
      new ChannelName('General'),
      $this->memberId(1),
    );

    $view = $this->repository->createChannel($channel);
    self::assertSame($channelId, $view->id);
    self::assertSame('channel', $view->subjectType);
    self::assertSame('participants', $view->visibility);
    self::assertSame('General', $view->name);
    self::assertSame($this->memberId(1), $view->createdByMemberId);
    $this->entityManager->clear();

    $this->addParticipant($channelId, $this->memberId(1));
    $this->addParticipant($channelId, $this->memberId(2));

    $channelView = $this->repository->findChannelById($channelId);
    self::assertNotNull($channelView);
    self::assertSame('General', $channelView->name);
    self::assertSame(2, $channelView->participantCount);

    // A subject thread is not a channel: findChannelById must reject it.
    $threadId = $this->conversationId(4);
    $this->createConversation($threadId, 'facility', 'facility-not-channel', 'subject');
    $this->entityManager->flush();
    $this->entityManager->clear();
    self::assertNull($this->repository->findChannelById($threadId));

    self::assertNull($this->repository->findChannelById($this->conversationId(99)));
  }

  #[Test]
  public function testTouchOnNewMessageIncrementsCountAndStampsLastMessageAt(): void
  {
    $id = $this->conversationId(5);
    $this->createConversation($id, 'facility', 'facility-touch', 'subject');
    $this->entityManager->flush();
    $this->entityManager->clear();

    $firstAt = new DateTimeImmutable('2026-06-15T09:30:00+00:00');
    $this->repository->touchOnNewMessage($id, $firstAt);
    $this->entityManager->clear();

    $afterFirst = $this->repository->findById($id);
    self::assertNotNull($afterFirst);
    self::assertSame(1, $afterFirst->messagesCount);
    self::assertNotNull($afterFirst->lastMessageAt);
    self::assertSame($firstAt->getTimestamp(), $afterFirst->lastMessageAt->getTimestamp());

    $this->repository->touchOnNewMessage($id, new DateTimeImmutable('2026-06-16T10:00:00+00:00'));
    $this->entityManager->clear();

    $afterSecond = $this->repository->findById($id);
    self::assertNotNull($afterSecond);
    self::assertSame(2, $afterSecond->messagesCount);
  }

  #[Test]
  public function testListAppliesFiltersOrderingAndPaginationClamps(): void
  {
    $a = $this->conversationId(10);
    $b = $this->conversationId(11);
    $c = $this->conversationId(12);
    $d = $this->conversationId(13);

    $this->createConversation($a, 'facility', 'fac-a', 'subject', null, false, new DateTimeImmutable('2026-06-01T00:00:00+00:00'));
    $this->createConversation($b, 'facility', 'fac-b', 'subject', null, false, new DateTimeImmutable('2026-06-03T00:00:00+00:00'));
    $this->createConversation($c, 'facility', 'fac-c', 'subject', null, false, null);
    $this->createConversation($d, 'equipment', 'eq-d', 'subject', null, true, new DateTimeImmutable('2026-06-02T00:00:00+00:00'));
    $this->entityManager->flush();
    $this->entityManager->clear();

    // Ordering: non-null last_message_at DESC first, null last.
    $all = $this->repository->list(self::ORG_ID, null, null, null, null, 1, 50);
    self::assertSame(4, $all->total);
    self::assertSame([$b, $d, $a, $c], $this->ids($all->items));

    // Subject-type filter.
    $facilities = $this->repository->list(self::ORG_ID, MessagingSubjectType::FACILITY, null, null, null, 1, 50);
    self::assertSame([$b, $a, $c], $this->ids($facilities->items));

    // Subject-id filter.
    $single = $this->repository->list(self::ORG_ID, null, 'fac-b', null, null, 1, 50);
    self::assertSame([$b], $this->ids($single->items));

    // Archived-flag filter (both directions).
    $archived = $this->repository->list(self::ORG_ID, null, null, true, null, 1, 50);
    self::assertSame([$d], $this->ids($archived->items));
    $active = $this->repository->list(self::ORG_ID, null, null, false, null, 1, 50);
    self::assertSame([$b, $a, $c], $this->ids($active->items));

    // Pagination.
    $firstPage = $this->repository->list(self::ORG_ID, null, null, null, null, 1, 2);
    self::assertSame(4, $firstPage->total);
    self::assertSame([$b, $d], $this->ids($firstPage->items));
    $secondPage = $this->repository->list(self::ORG_ID, null, null, null, null, 2, 2);
    self::assertSame([$a, $c], $this->ids($secondPage->items));

    // Clamps: page floored to 1, itemsPerPage floored to 1.
    $clampedLow = $this->repository->list(self::ORG_ID, null, null, null, null, 0, 0);
    self::assertSame(1, $clampedLow->page);
    self::assertSame(1, $clampedLow->itemsPerPage);
    self::assertSame([$b], $this->ids($clampedLow->items));

    // Clamp: itemsPerPage capped at 100.
    $clampedHigh = $this->repository->list(self::ORG_ID, null, null, null, null, 1, 500);
    self::assertSame(100, $clampedHigh->itemsPerPage);
  }

  #[Test]
  public function testListFiltersToUnreadConversationsForMember(): void
  {
    $unreadNoMarker = $this->conversationId(20);
    $readAfter = $this->conversationId(21);
    $unreadBefore = $this->conversationId(22);
    $neverMessaged = $this->conversationId(23);
    $member = $this->memberId(5);

    $this->createConversation($unreadNoMarker, 'facility', 'unread-1', 'subject', null, false, new DateTimeImmutable('2026-06-10T00:00:00+00:00'));
    $this->createConversation($readAfter, 'facility', 'unread-2', 'subject', null, false, new DateTimeImmutable('2026-06-10T00:00:00+00:00'));
    $this->createConversation($unreadBefore, 'facility', 'unread-3', 'subject', null, false, new DateTimeImmutable('2026-06-10T00:00:00+00:00'));
    $this->createConversation($neverMessaged, 'facility', 'unread-4', 'subject', null, false, null);
    $this->entityManager->flush();
    $this->entityManager->clear();

    $this->addReadMarker($readAfter, $member, new DateTimeImmutable('2026-06-11T00:00:00+00:00'));
    $this->addReadMarker($unreadBefore, $member, new DateTimeImmutable('2026-06-09T00:00:00+00:00'));

    $page = $this->repository->list(self::ORG_ID, null, null, null, $member, 1, 50);
    $ids = $this->ids($page->items);

    self::assertContains($unreadNoMarker, $ids, 'A conversation with activity and no read marker is unread.');
    self::assertContains($unreadBefore, $ids, 'A conversation newer than the read marker is unread.');
    self::assertNotContains($readAfter, $ids, 'A conversation older than the read marker is read.');
    self::assertNotContains($neverMessaged, $ids, 'A conversation without last_message_at can never be unread.');
  }

  #[Test]
  public function testListDirectConversationsForMemberIsScopedAndFilterable(): void
  {
    $mine = $this->conversationId(30);
    $mineArchived = $this->conversationId(31);
    $theirs = $this->conversationId(32);
    $member = $this->memberId(6);
    $other = $this->memberId(7);

    $this->createConversation($mine, 'direct', 'pair-1', 'participants', null, false, new DateTimeImmutable('2026-06-05T00:00:00+00:00'));
    $this->createConversation($mineArchived, 'direct', 'pair-2', 'participants', null, true, new DateTimeImmutable('2026-06-06T00:00:00+00:00'));
    $this->createConversation($theirs, 'direct', 'pair-3', 'participants', null, false, new DateTimeImmutable('2026-06-07T00:00:00+00:00'));
    $this->entityManager->flush();
    $this->entityManager->clear();

    $this->addParticipant($mine, $member);
    $this->addParticipant($mineArchived, $member);
    $this->addParticipant($theirs, $other);

    $all = $this->repository->listDirectConversationsForMember(self::ORG_ID, $member, null, 1, 50);
    self::assertSame([$mineArchived, $mine], $this->ids($all->items), 'Only the member\'s own direct conversations, most recent first.');
    self::assertSame('direct', $all->items[0]->subjectType);

    $active = $this->repository->listDirectConversationsForMember(self::ORG_ID, $member, false, 1, 50);
    self::assertSame([$mine], $this->ids($active->items));

    $archived = $this->repository->listDirectConversationsForMember(self::ORG_ID, $member, true, 1, 50);
    self::assertSame([$mineArchived], $this->ids($archived->items));
  }

  #[Test]
  public function testListChannelsForMemberAppliesArchivedFilterAndCountsParticipants(): void
  {
    $active = $this->conversationId(40);
    $archived = $this->conversationId(41);
    $member = $this->memberId(8);

    $this->createConversation($active, 'channel', null, 'participants', 'Chan Active', false, new DateTimeImmutable('2026-06-08T00:00:00+00:00'));
    $this->createConversation($archived, 'channel', null, 'participants', 'Chan Archived', true, new DateTimeImmutable('2026-06-09T00:00:00+00:00'));
    $this->entityManager->flush();
    $this->entityManager->clear();

    $this->addParticipant($active, $member);
    $this->addParticipant($active, $this->memberId(9));
    $this->addParticipant($archived, $member);

    $all = $this->repository->listChannelsForMember(self::ORG_ID, $member, null, 1, 50);
    self::assertSame([$archived, $active], $this->ids($all->items));
    $countsById = [];
    foreach ($all->items as $item) {
      $countsById[$item->id] = $item->participantCount;
    }
    self::assertSame(2, $countsById[$active]);
    self::assertSame(1, $countsById[$archived]);

    $activeOnly = $this->repository->listChannelsForMember(self::ORG_ID, $member, false, 1, 50);
    self::assertSame([$active], $this->ids($activeOnly->items));
  }

  #[Test]
  public function testFindChannelIdsBoundToTeamReturnsOnlyChannelsOfThatTeam(): void
  {
    $teamId = 'team-alpha';
    $boundA = $this->conversationId(50);
    $boundB = $this->conversationId(51);
    $otherTeam = $this->conversationId(52);
    $threadWithTeam = $this->conversationId(53);

    $this->createConversation($boundA, 'channel', null, 'participants', 'Alpha A', false, null, $teamId);
    $this->createConversation($boundB, 'channel', null, 'participants', 'Alpha B', false, null, $teamId);
    $this->createConversation($otherTeam, 'channel', null, 'participants', 'Beta', false, null, 'team-beta');
    // A non-channel row tagged with the same team must never be returned.
    $this->createConversation($threadWithTeam, 'facility', 'fac-team', 'subject', null, false, null, $teamId);
    $this->entityManager->flush();
    $this->entityManager->clear();

    $ids = $this->repository->findChannelIdsBoundToTeam(self::ORG_ID, $teamId);

    self::assertCount(2, $ids);
    self::assertContains($boundA, $ids);
    self::assertContains($boundB, $ids);
    self::assertNotContains($otherTeam, $ids);
    self::assertNotContains($threadWithTeam, $ids);
  }

  #[Test]
  public function testFindSubjectTypesByIdsMapsKnownIdsAndSkipsUnknown(): void
  {
    self::assertSame([], $this->repository->findSubjectTypesByIds([]));

    $facility = $this->conversationId(60);
    $channel = $this->conversationId(61);
    $this->createConversation($facility, 'facility', 'fac-map', 'subject');
    $this->createConversation($channel, 'channel', null, 'participants', 'Mapped');
    $this->entityManager->flush();
    $this->entityManager->clear();

    $map = $this->repository->findSubjectTypesByIds([$facility, $channel, $this->conversationId(97)]);

    self::assertCount(2, $map);
    self::assertSame('facility', $map[$facility]);
    self::assertSame('channel', $map[$channel]);
    self::assertArrayNotHasKey($this->conversationId(97), $map);
  }

  #[Test]
  public function testSaveThrowsWhenConversationDoesNotExist(): void
  {
    $ghost = Conversation::reconstitute(
      ConversationId::fromString($this->conversationId(96)),
      self::ORG_ID,
      MessagingSubjectType::CHANNEL,
      null,
      ConversationVisibility::PARTICIPANTS,
      null,
      0,
      false,
      new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      new ChannelName('Ghost'),
    );

    $this->expectException(MessagingNotFoundException::class);
    $this->repository->save($ghost);
  }

  /**
   * Extracts the ordered conversation ids of a page's items.
   *
   * @param list<ChannelView|ConversationView> $items the page items
   *
   * @return list<string> the item ids, in order
   */
  private function ids(array $items): array
  {
    return array_map(static fn (ChannelView|ConversationView $item): string => $item->id, $items);
  }

  private function conversationId(int $n): string
  {
    return sprintf('550e8400-e29b-41d4-a716-4466554810%02d', $n);
  }

  private function memberId(int $n): string
  {
    return sprintf('550e8400-e29b-41d4-a716-4466554820%02d', $n);
  }

  private function createOrganization(): void
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORG_ID;
    $organization->name = 'Messaging Conversation Listing Test';
    $organization->slug = 'messaging-conversation-listing-test';
    $organization->ownerUserId = self::OWNER_ID;
    $organization->createdByUserId = self::OWNER_ID;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;
    $this->entityManager->persist($organization);
  }

  private function createConversation(
    string $id,
    string $subjectType,
    ?string $subjectId,
    string $visibility,
    ?string $name = null,
    bool $isArchived = false,
    ?DateTimeImmutable $lastMessageAt = null,
    ?string $teamId = null,
  ): void {
    $organization = $this->entityManager->getReference(OrganizationRecord::class, self::ORG_ID);
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    $record = new MessagingConversationRecord();
    $record->id = $id;
    $record->organization = $organization;
    $record->subjectType = $subjectType;
    $record->subjectId = $subjectId;
    $record->visibility = $visibility;
    $record->name = $name;
    $record->teamId = $teamId;
    $record->lastMessageAt = $lastMessageAt;
    $record->messagesCount = 0;
    $record->isArchived = $isArchived;
    $record->createdAt = $now;
    $record->updatedAt = $now;
    $this->entityManager->persist($record);
  }

  private function addParticipant(string $conversationId, string $memberId): void
  {
    $this->entityManager->getConnection()->executeStatement(
      'INSERT INTO messaging_participants (conversation_id, organization_id, member_id, source, added_at) '
      . 'VALUES (:conversationId, :organizationId, :memberId, :source, :addedAt)',
      [
        'conversationId' => $conversationId,
        'organizationId' => self::ORG_ID,
        'memberId' => $memberId,
        'source' => 'manual',
        'addedAt' => new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      ],
      ['addedAt' => 'datetime_immutable'],
    );
  }

  private function addReadMarker(string $conversationId, string $memberId, DateTimeImmutable $lastReadAt): void
  {
    $this->entityManager->getConnection()->executeStatement(
      'INSERT INTO messaging_read_markers (conversation_id, organization_id, member_id, last_read_at, updated_at) '
      . 'VALUES (:conversationId, :organizationId, :memberId, :lastReadAt, :updatedAt)',
      [
        'conversationId' => $conversationId,
        'organizationId' => self::ORG_ID,
        'memberId' => $memberId,
        'lastReadAt' => $lastReadAt,
        'updatedAt' => $lastReadAt,
      ],
      ['lastReadAt' => 'datetime_immutable', 'updatedAt' => 'datetime_immutable'],
    );
  }

  private function cleanup(): void
  {
    $connection = $this->entityManager->getConnection();
    $connection->executeStatement('DELETE FROM messaging_read_markers WHERE organization_id = :organizationId', ['organizationId' => self::ORG_ID]);
    $connection->executeStatement('DELETE FROM messaging_participants WHERE organization_id = :organizationId', ['organizationId' => self::ORG_ID]);
    $connection->executeStatement('DELETE FROM messaging_conversations WHERE organization_id = :organizationId', ['organizationId' => self::ORG_ID]);
    $connection->executeStatement('DELETE FROM organizations WHERE id = :organizationId', ['organizationId' => self::ORG_ID]);
    $this->entityManager->clear();
  }
}
