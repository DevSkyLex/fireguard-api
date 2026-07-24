<?php

declare(strict_types=1);

namespace Tests\Integration\Messaging\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Messaging\Application\Contract\Channel\ParticipantView;
use Messaging\Infrastructure\Persistence\Doctrine\Record\MessagingConversationRecord;
use Messaging\Infrastructure\Persistence\Doctrine\Repository\MessagingParticipantRepository;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_map;

/**
 * Test MessagingParticipantRepository (read-side coverage).
 *
 * Exercises the branches the sibling `MessagingParticipantRepositoryTest`
 * leaves uncovered: `listParticipants` (view mapping + `added_at ASC`
 * ordering), `findCounterpartMemberIds` (both the empty-input early return
 * and the batch key/value resolution), `addMemberToChannels`, and the
 * empty-input early return of `removeMemberFromChannels`.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MessagingParticipantRepository::class)]
final class MessagingParticipantRepositoryCoverageTest extends KernelTestCase
{
  private const string ORG_ID = '660e8400-e29b-41d4-a716-446655445000';

  private const string CHANNEL_A = '660e8400-e29b-41d4-a716-446655445010';

  private const string CHANNEL_B = '660e8400-e29b-41d4-a716-446655445011';

  private const string CHANNEL_C = '660e8400-e29b-41d4-a716-446655445012';

  private EntityManagerInterface $entityManager;

  private MessagingParticipantRepository $repository;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    /** @var MessagingParticipantRepository $repository */
    $repository = static::getContainer()->get(MessagingParticipantRepository::class);
    $this->repository = $repository;

    $organization = $this->createOrganization();
    $this->createChannel(self::CHANNEL_A, $organization);
    $this->createChannel(self::CHANNEL_B, $organization);
    $this->createChannel(self::CHANNEL_C, $organization);
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
  public function testListParticipantsReturnsViewsOrderedByAddedAtAscendingAndScopedToTheChannel(): void
  {
    // Inserted out of chronological order to prove the ORDER BY added_at ASC.
    $this->repository->addParticipant(self::CHANNEL_A, self::ORG_ID, 'bob', 'lead', 'manual', new DateTimeImmutable('2026-01-02T12:00:00+00:00'));
    $this->repository->addParticipant(self::CHANNEL_A, self::ORG_ID, 'alice', null, 'team', new DateTimeImmutable('2026-01-01T12:00:00+00:00'));
    $this->repository->addParticipant(self::CHANNEL_A, self::ORG_ID, 'carol', null, 'manual', new DateTimeImmutable('2026-01-03T12:00:00+00:00'));
    // Belongs to another channel: must be excluded from the scoped listing.
    $this->repository->addParticipant(self::CHANNEL_B, self::ORG_ID, 'zoe', null, 'manual', new DateTimeImmutable('2026-01-01T12:00:00+00:00'));

    $views = $this->repository->listParticipants(self::CHANNEL_A);

    self::assertCount(3, $views);

    $memberIds = array_map(static fn (ParticipantView $view): string => $view->memberId, $views);
    self::assertSame(['alice', 'bob', 'carol'], $memberIds, 'Participants must be returned oldest-first.');

    $first = $views[0];
    self::assertInstanceOf(ParticipantView::class, $first);
    self::assertSame(self::CHANNEL_A, $first->conversationId);
    self::assertSame('alice', $first->memberId);
    self::assertNull($first->role);
    self::assertSame('team', $first->source);
    self::assertSame('2026-01-01', $first->addedAt->format('Y-m-d'));

    // The `role` column round-trips as the free-form label when set.
    self::assertSame('lead', $views[1]->role);
  }

  #[Test]
  public function testFindCounterpartMemberIdsResolvesTheOtherParticipantPerConversation(): void
  {
    $now = new DateTimeImmutable('2026-01-01T12:00:00+00:00');
    // Two direct-style channels, each seeded with exactly two members.
    $this->repository->addParticipant(self::CHANNEL_B, self::ORG_ID, 'alice', null, 'manual', $now);
    $this->repository->addParticipant(self::CHANNEL_B, self::ORG_ID, 'bob', null, 'manual', $now);
    $this->repository->addParticipant(self::CHANNEL_C, self::ORG_ID, 'alice', null, 'manual', $now);
    $this->repository->addParticipant(self::CHANNEL_C, self::ORG_ID, 'carol', null, 'manual', $now);

    $counterparts = $this->repository->findCounterpartMemberIds([self::CHANNEL_B, self::CHANNEL_C], 'alice');

    self::assertCount(2, $counterparts);
    self::assertArrayHasKey(self::CHANNEL_B, $counterparts);
    self::assertArrayHasKey(self::CHANNEL_C, $counterparts);
    self::assertSame('bob', $counterparts[self::CHANNEL_B]);
    self::assertSame('carol', $counterparts[self::CHANNEL_C]);
  }

  #[Test]
  public function testFindCounterpartMemberIdsReturnsEmptyForEmptyInput(): void
  {
    self::assertSame([], $this->repository->findCounterpartMemberIds([], 'alice'));
  }

  #[Test]
  public function testAddMemberToChannelsAddsTheMemberToEveryChannelIdempotently(): void
  {
    $this->repository->addMemberToChannels([self::CHANNEL_A, self::CHANNEL_B], self::ORG_ID, 'dave', 'team');
    // A second pass must be a no-op (ON CONFLICT DO NOTHING), not a duplicate.
    $this->repository->addMemberToChannels([self::CHANNEL_A, self::CHANNEL_B], self::ORG_ID, 'dave', 'team');

    self::assertTrue($this->repository->isParticipant(self::CHANNEL_A, 'dave'));
    self::assertTrue($this->repository->isParticipant(self::CHANNEL_B, 'dave'));
    self::assertFalse($this->repository->isParticipant(self::CHANNEL_C, 'dave'));

    $channelIds = $this->repository->listChannelIdsForMember(self::ORG_ID, 'dave');
    self::assertContains(self::CHANNEL_A, $channelIds);
    self::assertContains(self::CHANNEL_B, $channelIds);
    self::assertNotContains(self::CHANNEL_C, $channelIds);
  }

  #[Test]
  public function testRemoveMemberFromChannelsIsANoOpForEmptyChannelList(): void
  {
    $this->repository->addParticipant(self::CHANNEL_A, self::ORG_ID, 'eve', null, 'team', new DateTimeImmutable('2026-01-01T12:00:00+00:00'));

    $this->repository->removeMemberFromChannels([], 'eve');

    self::assertTrue($this->repository->isParticipant(self::CHANNEL_A, 'eve'), 'An empty channel list must short-circuit without touching any row.');
  }

  private function createOrganization(): OrganizationRecord
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORG_ID;
    $organization->name = 'Messaging Participant Repository Coverage Test';
    $organization->slug = 'messaging-participant-repository-coverage-test';
    $organization->ownerUserId = '660e8400-e29b-41d4-a716-446655445900';
    $organization->createdByUserId = '660e8400-e29b-41d4-a716-446655445900';
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;
    $this->entityManager->persist($organization);

    return $organization;
  }

  private function createChannel(string $id, OrganizationRecord $organization): void
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    $record = new MessagingConversationRecord();
    $record->id = $id;
    $record->organization = $organization;
    $record->subjectType = 'channel';
    $record->subjectId = null;
    $record->visibility = 'participants';
    $record->name = 'Channel ' . $id;
    $record->messagesCount = 0;
    $record->isArchived = false;
    $record->createdAt = $now;
    $record->updatedAt = $now;
    $this->entityManager->persist($record);
  }

  private function cleanup(): void
  {
    $connection = $this->entityManager->getConnection();
    $connection->executeStatement(
      'DELETE FROM messaging_participants WHERE organization_id = :organizationId',
      ['organizationId' => self::ORG_ID],
    );
    $connection->executeStatement(
      'DELETE FROM messaging_conversations WHERE organization_id = :organizationId',
      ['organizationId' => self::ORG_ID],
    );
    $connection->executeStatement(
      'DELETE FROM organizations WHERE id = :organizationId',
      ['organizationId' => self::ORG_ID],
    );
    $this->entityManager->clear();
  }
}
