<?php

declare(strict_types=1);

namespace Tests\Integration\Messaging\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Messaging\Infrastructure\Persistence\Doctrine\Record\MessagingConversationRecord;
use Messaging\Infrastructure\Persistence\Doctrine\Repository\MessagingParticipantRepository;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[CoversClass(MessagingParticipantRepository::class)]
final class MessagingParticipantRepositoryTest extends KernelTestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655444000';

  private const string CHANNEL_ID = '550e8400-e29b-41d4-a716-446655444010';

  private EntityManagerInterface $entityManager;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->removeOrganization(self::ORG_ID);

    $organization = $this->createOrganization(self::ORG_ID);
    $this->createChannel(self::CHANNEL_ID, $organization);
    $this->entityManager->flush();
  }

  protected function tearDown(): void
  {
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testAddParticipantIsIdempotent(): void
  {
    $repository = new MessagingParticipantRepository($this->entityManager);
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    $repository->addParticipant(self::CHANNEL_ID, self::ORG_ID, 'member-1', null, 'manual', $now);
    $repository->addParticipant(self::CHANNEL_ID, self::ORG_ID, 'member-1', null, 'manual', $now);

    self::assertSame(['member-1'], $repository->listMemberIds(self::CHANNEL_ID));
    self::assertTrue($repository->isParticipant(self::CHANNEL_ID, 'member-1'));
  }

  #[Test]
  public function testRemoveParticipantIsIdempotent(): void
  {
    $repository = new MessagingParticipantRepository($this->entityManager);
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    $repository->addParticipant(self::CHANNEL_ID, self::ORG_ID, 'member-1', null, 'manual', $now);
    $repository->removeParticipant(self::CHANNEL_ID, 'member-1');
    $repository->removeParticipant(self::CHANNEL_ID, 'member-1');

    self::assertFalse($repository->isParticipant(self::CHANNEL_ID, 'member-1'));
    self::assertSame([], $repository->listMemberIds(self::CHANNEL_ID));
  }

  #[Test]
  public function testListChannelIdsForMemberReturnsOnlyTheMembersChannels(): void
  {
    $repository = new MessagingParticipantRepository($this->entityManager);
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    $repository->addParticipant(self::CHANNEL_ID, self::ORG_ID, 'member-1', null, 'manual', $now);

    self::assertSame([self::CHANNEL_ID], $repository->listChannelIdsForMember(self::ORG_ID, 'member-1'));
    self::assertSame([], $repository->listChannelIdsForMember(self::ORG_ID, 'member-2'));
  }

  #[Test]
  public function testReplaceParticipantsOnlyTouchesTeamSourcedRows(): void
  {
    $repository = new MessagingParticipantRepository($this->entityManager);
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    // A manually added participant, independent of any team.
    $repository->addParticipant(self::CHANNEL_ID, self::ORG_ID, 'manual-member', null, 'manual', $now);
    // A pre-existing team-sourced participant who left the team.
    $repository->addParticipant(self::CHANNEL_ID, self::ORG_ID, 'stale-team-member', null, 'team', $now);

    $repository->replaceParticipants(self::CHANNEL_ID, self::ORG_ID, ['new-team-member']);

    $memberIds = $repository->listMemberIds(self::CHANNEL_ID);
    self::assertContains('manual-member', $memberIds, 'A manual participant must never be removed by a team resync.');
    self::assertContains('new-team-member', $memberIds, 'A new active team member must be added.');
    self::assertNotContains('stale-team-member', $memberIds, 'A team member no longer active must be pruned.');
  }

  #[Test]
  public function testRemoveMemberFromChannelsOnlyRemovesTheTeamSourcedRow(): void
  {
    $repository = new MessagingParticipantRepository($this->entityManager);
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    $repository->addParticipant(self::CHANNEL_ID, self::ORG_ID, 'member-1', null, 'manual', $now);

    $repository->removeMemberFromChannels([self::CHANNEL_ID], 'member-1');

    self::assertTrue($repository->isParticipant(self::CHANNEL_ID, 'member-1'), 'A manual participant must be retained when the team-scoped removal runs.');
  }

  #[Test]
  public function testRemoveMemberFromAllChannelsRemovesRegardlessOfSource(): void
  {
    $repository = new MessagingParticipantRepository($this->entityManager);
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    $repository->addParticipant(self::CHANNEL_ID, self::ORG_ID, 'member-1', null, 'manual', $now);

    $repository->removeMemberFromAllChannels(self::ORG_ID, 'member-1');

    self::assertFalse($repository->isParticipant(self::CHANNEL_ID, 'member-1'));
  }

  private function createOrganization(string $id): OrganizationRecord
  {
    $organization = new OrganizationRecord();
    $organization->id = $id;
    $organization->name = 'Messaging Participant Repository Test';
    $organization->slug = 'messaging-participant-repository-test-' . $id;
    $organization->ownerUserId = '550e8400-e29b-41d4-a716-446655443900';
    $organization->createdByUserId = '550e8400-e29b-41d4-a716-446655443900';
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;
    $this->entityManager->persist($organization);

    return $organization;
  }

  private function createChannel(string $id, OrganizationRecord $organization): MessagingConversationRecord
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    $record = new MessagingConversationRecord();
    $record->id = $id;
    $record->organization = $organization;
    $record->subjectType = 'channel';
    $record->subjectId = null;
    $record->visibility = 'participants';
    $record->name = 'General';
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
