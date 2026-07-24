<?php

declare(strict_types=1);

namespace Tests\Integration\Messaging\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Messaging\Domain\Model\Message\Message;
use Messaging\Domain\Service\MentionExtractor;
use Messaging\Domain\ValueObject\MessageId;
use Messaging\Infrastructure\Persistence\Doctrine\Record\MessagingConversationRecord;
use Messaging\Infrastructure\Persistence\Doctrine\Repository\{MessagingMessageRepository, MessagingSavedMessageRepository};
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function mt_rand;
use function sprintf;

/**
 * Test MessagingSavedMessageRepositoryTest.
 *
 * Executes the REAL `save()`/`unsave()`/`findSavedMessageIds()` DQL/DBAL
 * against the test database — a mocked QueryBuilder would assert call shape
 * without ever parsing the SQL, and would not catch a broken IN-clause
 * parameter.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MessagingSavedMessageRepository::class)]
final class MessagingSavedMessageRepositoryTest extends KernelTestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655447000';

  private const string CONVERSATION_ID = '550e8400-e29b-41d4-a716-446655447010';

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
  public function testSaveIsIdempotentWhenSavingTwice(): void
  {
    $messages = new MessagingMessageRepository($this->entityManager);
    $savedMessages = new MessagingSavedMessageRepository($this->entityManager);

    $message = $this->appendMessage($messages, 'Save me');
    $now = new DateTimeImmutable('2026-01-01T00:00:01+00:00');

    $savedMessages->save((string) $message->id(), self::ORG_ID, 'member-1', $now);
    $savedMessages->save((string) $message->id(), self::ORG_ID, 'member-1', $now);

    $ids = $savedMessages->findSavedMessageIds('member-1', [(string) $message->id()]);

    self::assertCount(1, $ids, 'Saving twice must be a silent no-op, not a duplicate row.');
  }

  #[Test]
  public function testUnsaveIsIdempotentOnANeverSavedRow(): void
  {
    $messages = new MessagingMessageRepository($this->entityManager);
    $savedMessages = new MessagingSavedMessageRepository($this->entityManager);

    $message = $this->appendMessage($messages, 'Never saved');

    // Must not throw, even though nothing exists to remove.
    $savedMessages->unsave((string) $message->id(), 'member-1');

    self::assertSame([], $savedMessages->findSavedMessageIds('member-1', [(string) $message->id()]));
  }

  #[Test]
  public function testFindSavedMessageIdsIsScopedToOneMember(): void
  {
    $messages = new MessagingMessageRepository($this->entityManager);
    $savedMessages = new MessagingSavedMessageRepository($this->entityManager);

    $message = $this->appendMessage($messages, 'Shared save target');
    $now = new DateTimeImmutable('2026-01-01T00:00:01+00:00');

    $savedMessages->save((string) $message->id(), self::ORG_ID, 'member-1', $now);

    self::assertSame([(string) $message->id()], $savedMessages->findSavedMessageIds('member-1', [(string) $message->id()]));
    self::assertSame([], $savedMessages->findSavedMessageIds('member-2', [(string) $message->id()]), "Another member must never see member-1's save.");
  }

  #[Test]
  public function testFindSavedMessageIdsBatchesAcrossSeveralMessagesInOneQuery(): void
  {
    $messages = new MessagingMessageRepository($this->entityManager);
    $savedMessages = new MessagingSavedMessageRepository($this->entityManager);

    $first = $this->appendMessage($messages, 'First message');
    $second = $this->appendMessage($messages, 'Second message');
    $unrelated = $this->appendMessage($messages, 'Never saved');

    $now = new DateTimeImmutable('2026-01-01T00:00:01+00:00');
    $savedMessages->save((string) $first->id(), self::ORG_ID, 'member-1', $now);
    $savedMessages->save((string) $second->id(), self::ORG_ID, 'member-1', $now);

    $ids = $savedMessages->findSavedMessageIds('member-1', [(string) $first->id(), (string) $second->id(), (string) $unrelated->id()]);

    self::assertCount(2, $ids);
    self::assertContains((string) $first->id(), $ids);
    self::assertContains((string) $second->id(), $ids);
    self::assertNotContains((string) $unrelated->id(), $ids);
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
    $organization->name = 'Messaging Saved Message Repository Test';
    $organization->slug = 'messaging-saved-message-repository-test-' . $id;
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
