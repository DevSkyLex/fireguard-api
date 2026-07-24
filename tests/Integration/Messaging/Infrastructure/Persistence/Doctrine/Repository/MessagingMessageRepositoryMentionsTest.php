<?php

declare(strict_types=1);

namespace Tests\Integration\Messaging\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Messaging\Application\Contract\Message\MessageView;
use Messaging\Domain\Model\Message\Message;
use Messaging\Domain\ValueObject\MessageId;
use Messaging\Infrastructure\Persistence\Doctrine\Record\MessagingConversationRecord;
use Messaging\Infrastructure\Persistence\Doctrine\Repository\{MessagingConversationRepository, MessagingMessageRepository, MessagingReadMarkerRepository};
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Shared\Application\Factory\UuidFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_map;
use function mt_rand;
use function sprintf;

/**
 * Test MessagingMessageRepositoryMentionsTest.
 *
 * Executes the REAL `listMentionsForMember()` SQL against the test database
 * — the unified inbox mention source's one bounded candidate query. It
 * relies on Postgres `json_array_elements_text()` for exact-value matching
 * inside the `mentions` JSON array; a mocked QueryBuilder would assert call
 * shape without ever parsing that SQL, and would not catch a substring
 * false-positive or a broken cursor/limit/scope predicate (mirrors
 * `MessagingMessageRepositorySavedTest`'s rationale for `listSavedByMember()`).
 * Also exercises `MessagingConversationRepository::findSubjectTypesByIds()`
 * and `MessagingReadMarkerRepository::lastReadAtByConversations()`, the two
 * sibling batch-lookups the same inbox source adapter relies on, against
 * the same fixtures.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MessagingMessageRepository::class)]
#[CoversClass(MessagingConversationRepository::class)]
#[CoversClass(MessagingReadMarkerRepository::class)]
final class MessagingMessageRepositoryMentionsTest extends KernelTestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655447500';

  private const string OTHER_ORG_ID = '550e8400-e29b-41d4-a716-446655447501';

  private const string CONVERSATION_ID = '550e8400-e29b-41d4-a716-446655447510';

  private const string CHANNEL_CONVERSATION_ID = '550e8400-e29b-41d4-a716-446655447511';

  private const string OTHER_ORG_CONVERSATION_ID = '550e8400-e29b-41d4-a716-446655447512';

  private const string MEMBER_ID = '550e8400-e29b-41d4-a716-446655448800';

  private const string OTHER_MEMBER_ID = '550e8400-e29b-41d4-a716-446655448801';

  private EntityManagerInterface $entityManager;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->removeOrganization(self::ORG_ID);
    $this->removeOrganization(self::OTHER_ORG_ID);

    $organization = $this->createOrganization(self::ORG_ID);
    $otherOrganization = $this->createOrganization(self::OTHER_ORG_ID);
    $this->createConversation(self::CONVERSATION_ID, $organization, 'facility');
    $this->createConversation(self::CHANNEL_CONVERSATION_ID, $organization, 'channel');
    $this->createConversation(self::OTHER_ORG_CONVERSATION_ID, $otherOrganization, 'facility');
    $this->entityManager->flush();
  }

  protected function tearDown(): void
  {
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testListMentionsForMemberOnlyReturnsMessagesThatMentionTheGivenMember(): void
  {
    $messages = new MessagingMessageRepository($this->entityManager);
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    $mentioning = $this->appendMessage($messages, self::CONVERSATION_ID, self::ORG_ID, 'author-1', [self::MEMBER_ID], $now);
    $this->appendMessage($messages, self::CONVERSATION_ID, self::ORG_ID, 'author-1', [self::OTHER_MEMBER_ID], $now);

    $ids = $this->ids($messages->listMentionsForMember(self::ORG_ID, self::MEMBER_ID, null, 20));

    self::assertSame([$mentioning->id], $ids);
  }

  #[Test]
  public function testListMentionsForMemberDoesNotMatchAnIdThatMerelyContainsTheMemberIdAsASubstring(): void
  {
    $messages = new MessagingMessageRepository($this->entityManager);
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    // A similar-but-different id (the target id with a suffix) must never
    // false-positive-match: json_array_elements_text() compares each array
    // element for EXACT equality, never a substring/LIKE match.
    $this->appendMessage($messages, self::CONVERSATION_ID, self::ORG_ID, 'author-1', [self::MEMBER_ID . '-extra'], $now);

    $ids = $this->ids($messages->listMentionsForMember(self::ORG_ID, self::MEMBER_ID, null, 20));

    self::assertSame([], $ids);
  }

  #[Test]
  public function testListMentionsForMemberExcludesTombstonedMessages(): void
  {
    $messages = new MessagingMessageRepository($this->entityManager);
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    $visible = $this->appendMessage($messages, self::CONVERSATION_ID, self::ORG_ID, 'author-1', [self::MEMBER_ID], $now);
    $this->appendMessage($messages, self::CONVERSATION_ID, self::ORG_ID, 'author-1', [self::MEMBER_ID], $now, deletedAt: $now);

    $ids = $this->ids($messages->listMentionsForMember(self::ORG_ID, self::MEMBER_ID, null, 20));

    self::assertSame([$visible->id], $ids);
  }

  #[Test]
  public function testListMentionsForMemberExcludesTheMentionedMembersOwnMessages(): void
  {
    $messages = new MessagingMessageRepository($this->entityManager);
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    // A member is never notified of mentioning themselves.
    $this->appendMessage($messages, self::CONVERSATION_ID, self::ORG_ID, self::MEMBER_ID, [self::MEMBER_ID], $now);
    $fromSomeoneElse = $this->appendMessage($messages, self::CONVERSATION_ID, self::ORG_ID, 'author-1', [self::MEMBER_ID], $now);

    $ids = $this->ids($messages->listMentionsForMember(self::ORG_ID, self::MEMBER_ID, null, 20));

    self::assertSame([$fromSomeoneElse->id], $ids);
  }

  #[Test]
  public function testListMentionsForMemberNeverLeaksAMentionFromAnotherOrganization(): void
  {
    $messages = new MessagingMessageRepository($this->entityManager);
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    $this->appendMessage($messages, self::OTHER_ORG_CONVERSATION_ID, self::OTHER_ORG_ID, 'author-1', [self::MEMBER_ID], $now);
    $inThisOrg = $this->appendMessage($messages, self::CONVERSATION_ID, self::ORG_ID, 'author-1', [self::MEMBER_ID], $now);

    $ids = $this->ids($messages->listMentionsForMember(self::ORG_ID, self::MEMBER_ID, null, 20));

    self::assertSame([$inThisOrg->id], $ids);
  }

  #[Test]
  public function testListMentionsForMemberHonoursTheBeforeCursorAndOrdersNewestFirst(): void
  {
    $messages = new MessagingMessageRepository($this->entityManager);

    $first = $this->appendMessage($messages, self::CONVERSATION_ID, self::ORG_ID, 'author-1', [self::MEMBER_ID], new DateTimeImmutable('2026-01-01T00:00:01+00:00'));
    $second = $this->appendMessage($messages, self::CONVERSATION_ID, self::ORG_ID, 'author-1', [self::MEMBER_ID], new DateTimeImmutable('2026-01-01T00:00:02+00:00'));
    $third = $this->appendMessage($messages, self::CONVERSATION_ID, self::ORG_ID, 'author-1', [self::MEMBER_ID], new DateTimeImmutable('2026-01-01T00:00:03+00:00'));

    $everything = $this->ids($messages->listMentionsForMember(self::ORG_ID, self::MEMBER_ID, null, 20));
    self::assertSame([$third->id, $second->id, $first->id], $everything, 'Newest first, no cursor.');

    $beforeThird = $this->ids($messages->listMentionsForMember(self::ORG_ID, self::MEMBER_ID, new DateTimeImmutable('2026-01-01T00:00:03+00:00'), 20));
    self::assertSame([$second->id, $first->id], $beforeThird, 'Strictly before the cursor: the cursor item itself is excluded.');

    $beforeSecond = $this->ids($messages->listMentionsForMember(self::ORG_ID, self::MEMBER_ID, new DateTimeImmutable('2026-01-01T00:00:02+00:00'), 20));
    self::assertSame([$first->id], $beforeSecond);
  }

  #[Test]
  public function testListMentionsForMemberHonoursTheLimit(): void
  {
    $messages = new MessagingMessageRepository($this->entityManager);

    $second = $this->appendMessage($messages, self::CONVERSATION_ID, self::ORG_ID, 'author-1', [self::MEMBER_ID], new DateTimeImmutable('2026-01-01T00:00:02+00:00'));
    $third = $this->appendMessage($messages, self::CONVERSATION_ID, self::ORG_ID, 'author-1', [self::MEMBER_ID], new DateTimeImmutable('2026-01-01T00:00:03+00:00'));
    $this->appendMessage($messages, self::CONVERSATION_ID, self::ORG_ID, 'author-1', [self::MEMBER_ID], new DateTimeImmutable('2026-01-01T00:00:01+00:00'));

    $limited = $this->ids($messages->listMentionsForMember(self::ORG_ID, self::MEMBER_ID, null, 2));

    self::assertSame([$third->id, $second->id], $limited, 'Only the 2 most recent, even though 3 exist.');
  }

  #[Test]
  public function testFindSubjectTypesByIdsBatchResolvesEveryConversationAndOmitsUnknownIds(): void
  {
    /** @var UuidFactory $uuidFactory */
    $uuidFactory = self::getContainer()->get(UuidFactory::class);
    $conversations = new MessagingConversationRepository($this->entityManager, $uuidFactory);

    $map = $conversations->findSubjectTypesByIds([
      self::CONVERSATION_ID,
      self::CHANNEL_CONVERSATION_ID,
      '550e8400-e29b-41d4-a716-446655449999',
    ]);

    self::assertSame([
      self::CONVERSATION_ID => 'facility',
      self::CHANNEL_CONVERSATION_ID => 'channel',
    ], $map);
  }

  #[Test]
  public function testLastReadAtByConversationsReturnsOnlyThisMembersMarkedConversations(): void
  {
    $readMarkers = new MessagingReadMarkerRepository($this->entityManager);
    $readAt = new DateTimeImmutable('2026-01-01T00:00:05+00:00');

    $readMarkers->upsert(self::CONVERSATION_ID, self::ORG_ID, self::MEMBER_ID, $readAt, null);

    $map = $readMarkers->lastReadAtByConversations(self::MEMBER_ID, [self::CONVERSATION_ID, self::CHANNEL_CONVERSATION_ID]);

    self::assertEquals($readAt, $map[self::CONVERSATION_ID]);
    self::assertArrayNotHasKey(self::CHANNEL_CONVERSATION_ID, $map, 'No marker at all: absent, never a false "read".');
  }

  /**
   * @param list<MessageView> $page
   *
   * @return list<string>
   */
  private function ids(array $page): array
  {
    return array_map(static fn (MessageView $item): string => $item->id, $page);
  }

  /**
   * @param list<string> $mentions the mentioned organization member ids
   */
  private function appendMessage(
    MessagingMessageRepository $repository,
    string $conversationId,
    string $organizationId,
    string $authorMemberId,
    array $mentions,
    DateTimeImmutable $createdAt,
    ?DateTimeImmutable $deletedAt = null,
  ): MessageView {
    $message = Message::reconstitute(
      id: MessageId::fromString($this->uuid()),
      conversationId: $conversationId,
      organizationId: $organizationId,
      authorMemberId: $authorMemberId,
      body: 'Please check this.',
      mentions: $mentions,
      editedAt: null,
      deletedAt: $deletedAt,
      deletedByMemberId: null !== $deletedAt ? $authorMemberId : null,
      createdAt: $createdAt,
      updatedAt: $createdAt,
    );

    return $repository->append($message);
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
    $organization->name = 'Messaging Message Repository Mentions Test';
    $organization->slug = 'messaging-message-repository-mentions-test-' . $id;
    $organization->ownerUserId = '550e8400-e29b-41d4-a716-446655443900';
    $organization->createdByUserId = '550e8400-e29b-41d4-a716-446655443900';
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;
    $this->entityManager->persist($organization);

    return $organization;
  }

  private function createConversation(string $id, OrganizationRecord $organization, string $subjectType): MessagingConversationRecord
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    $record = new MessagingConversationRecord();
    $record->id = $id;
    $record->organization = $organization;
    $record->subjectType = $subjectType;
    $record->subjectId = 'channel' === $subjectType ? null : '550e8400-e29b-41d4-a716-446655449001';
    $record->visibility = 'channel' === $subjectType ? 'participants' : 'subject';
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
