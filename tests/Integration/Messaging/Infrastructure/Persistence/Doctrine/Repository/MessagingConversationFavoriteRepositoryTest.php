<?php

declare(strict_types=1);

namespace Tests\Integration\Messaging\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Messaging\Infrastructure\Persistence\Doctrine\Record\MessagingConversationRecord;
use Messaging\Infrastructure\Persistence\Doctrine\Repository\MessagingConversationFavoriteRepository;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test MessagingConversationFavoriteRepositoryTest.
 *
 * Executes the REAL `favorite()`/`unfavorite()`/`findFavoritedConversationIds()`
 * DQL/DBAL against the test database — a mocked QueryBuilder would assert
 * call shape without ever parsing the SQL, and would not catch a broken
 * IN-clause parameter.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MessagingConversationFavoriteRepository::class)]
final class MessagingConversationFavoriteRepositoryTest extends KernelTestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655448000';

  private const string CONVERSATION_ID = '550e8400-e29b-41d4-a716-446655448010';

  private const string OTHER_CONVERSATION_ID = '550e8400-e29b-41d4-a716-446655448011';

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
  public function testFavoriteIsIdempotentWhenFavoritingTwice(): void
  {
    $favorites = new MessagingConversationFavoriteRepository($this->entityManager);
    $now = new DateTimeImmutable('2026-01-01T00:00:01+00:00');

    $favorites->favorite(self::CONVERSATION_ID, self::ORG_ID, 'member-1', $now);
    $favorites->favorite(self::CONVERSATION_ID, self::ORG_ID, 'member-1', $now);

    $ids = $favorites->findFavoritedConversationIds('member-1', [self::CONVERSATION_ID]);

    self::assertCount(1, $ids, 'Favoriting twice must be a silent no-op, not a duplicate row.');
  }

  #[Test]
  public function testUnfavoriteIsIdempotentOnANeverFavoritedRow(): void
  {
    $favorites = new MessagingConversationFavoriteRepository($this->entityManager);

    // Must not throw, even though nothing exists to remove.
    $favorites->unfavorite(self::CONVERSATION_ID, 'member-1');

    self::assertSame([], $favorites->findFavoritedConversationIds('member-1', [self::CONVERSATION_ID]));
  }

  #[Test]
  public function testFindFavoritedConversationIdsIsScopedToOneMember(): void
  {
    $favorites = new MessagingConversationFavoriteRepository($this->entityManager);
    $now = new DateTimeImmutable('2026-01-01T00:00:01+00:00');

    $favorites->favorite(self::CONVERSATION_ID, self::ORG_ID, 'member-1', $now);

    self::assertSame([self::CONVERSATION_ID], $favorites->findFavoritedConversationIds('member-1', [self::CONVERSATION_ID]));
    self::assertSame([], $favorites->findFavoritedConversationIds('member-2', [self::CONVERSATION_ID]), "Another member must never see member-1's favorite.");
  }

  #[Test]
  public function testFindFavoritedConversationIdsBatchesAcrossSeveralConversationsInOneQuery(): void
  {
    $favorites = new MessagingConversationFavoriteRepository($this->entityManager);
    $now = new DateTimeImmutable('2026-01-01T00:00:01+00:00');

    $favorites->favorite(self::CONVERSATION_ID, self::ORG_ID, 'member-1', $now);

    $ids = $favorites->findFavoritedConversationIds('member-1', [self::CONVERSATION_ID, self::OTHER_CONVERSATION_ID]);

    self::assertSame([self::CONVERSATION_ID], $ids);
  }

  #[Test]
  public function testUnfavoriteRemovesAPreviouslyFavoritedConversation(): void
  {
    $favorites = new MessagingConversationFavoriteRepository($this->entityManager);
    $now = new DateTimeImmutable('2026-01-01T00:00:01+00:00');

    $favorites->favorite(self::CONVERSATION_ID, self::ORG_ID, 'member-1', $now);
    self::assertSame([self::CONVERSATION_ID], $favorites->findFavoritedConversationIds('member-1', [self::CONVERSATION_ID]));

    $favorites->unfavorite(self::CONVERSATION_ID, 'member-1');

    self::assertSame([], $favorites->findFavoritedConversationIds('member-1', [self::CONVERSATION_ID]));
  }

  private function createOrganization(string $id): OrganizationRecord
  {
    $organization = new OrganizationRecord();
    $organization->id = $id;
    $organization->name = 'Messaging Conversation Favorite Repository Test';
    $organization->slug = 'messaging-conversation-favorite-repository-test-' . $id;
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
