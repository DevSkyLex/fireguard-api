<?php

declare(strict_types=1);

namespace Tests\Integration\Messaging\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Messaging\Domain\Exception\MessagingNotFoundException;
use Messaging\Domain\Model\Message\Message;
use Messaging\Domain\Service\MentionExtractor;
use Messaging\Domain\ValueObject\{MessageId, MessageReference};
use Messaging\Infrastructure\Persistence\Doctrine\Record\MessagingConversationRecord;
use Messaging\Infrastructure\Persistence\Doctrine\Repository\MessagingMessageRepository;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function mt_rand;
use function sprintf;

/**
 * Test MessagingMessageRepositoryReferencesTest.
 *
 * Executes the REAL persistence round-trip of the target against the test
 * database, covering the branches the sibling tests (pinned/saved/mentions/
 * replies/activity) never reach: the structured-references (B3) column
 * surviving `append()`/`save()` and hydrating back through both `view()`
 * (`referencesForStorage`) and `aggregate()` (`referencesFromStorage`), plus
 * the not-found paths of `findById()`/`findAggregateById()` (returning null)
 * and `save()` (throwing `MessagingNotFoundException`). A mocked entity
 * manager would assert call shape without ever serializing the JSON column
 * or resolving the identity map, and would not catch a broken references
 * round-trip or not-found branch.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MessagingMessageRepository::class)]
final class MessagingMessageRepositoryReferencesTest extends KernelTestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655449500';

  private const string CONVERSATION_ID = '550e8400-e29b-41d4-a716-446655449510';

  private const string AUTHOR_ID = '550e8400-e29b-41d4-a716-446655449801';

  private const string UNKNOWN_MESSAGE_ID = '550e8400-e29b-41d4-a716-446655449999';

  private EntityManagerInterface $entityManager;

  private MessagingMessageRepository $repository;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    /** @var MessagingMessageRepository $repository */
    $repository = static::getContainer()->get(MessagingMessageRepository::class);
    $this->repository = $repository;

    $organization = $this->createOrganization();
    $this->createConversation($organization);
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
  public function testAppendPersistsStructuredReferencesSurvivingTheDatabaseRoundTrip(): void
  {
    $references = [
      MessageReference::fromArray(['type' => 'intervention', 'id' => 'int-1', 'label' => 'Panel A', 'code' => 'INT-001']),
      MessageReference::fromArray(['type' => 'equipment', 'id' => 'eq-1', 'label' => null, 'code' => null]),
    ];
    $message = $this->makeMessage('See the linked records.', $references);

    // The view returned straight from append() already carries the storage
    // shape produced by referencesForStorage()'s non-empty branch.
    $appended = $this->repository->append($message);
    self::assertEquals([
      ['type' => 'intervention', 'id' => 'int-1', 'label' => 'Panel A', 'code' => 'INT-001'],
      ['type' => 'equipment', 'id' => 'eq-1', 'label' => null, 'code' => null],
    ], $appended->references);

    // Re-read from a cleared identity map: this is the genuine DB round-trip
    // through the JSON `references` column and view()'s `?? []` mapping.
    $this->entityManager->clear();
    $reloaded = $this->repository->findById((string) $message->id());
    self::assertNotNull($reloaded);
    self::assertSame((string) $message->id(), $reloaded->id);
    self::assertSame(self::CONVERSATION_ID, $reloaded->conversationId);
    self::assertSame(self::ORG_ID, $reloaded->organizationId);
    self::assertEquals([
      ['type' => 'intervention', 'id' => 'int-1', 'label' => 'Panel A', 'code' => 'INT-001'],
      ['type' => 'equipment', 'id' => 'eq-1', 'label' => null, 'code' => null],
    ], $reloaded->references);
  }

  #[Test]
  public function testFindAggregateByIdReconstitutesTheMessageWithItsReferences(): void
  {
    $message = $this->makeMessage(
      'Reference this facility.',
      [MessageReference::fromArray(['type' => 'facility', 'id' => 'fac-1', 'label' => 'Building A', 'code' => 'FAC-001'])],
    );
    $this->repository->append($message);
    $this->entityManager->clear();

    // aggregate() hydrates the references via referencesFromStorage()'s
    // non-null branch into MessageReference value objects.
    $aggregate = $this->repository->findAggregateById((string) $message->id());
    self::assertNotNull($aggregate);
    self::assertSame((string) $message->id(), (string) $aggregate->id());
    self::assertSame(self::CONVERSATION_ID, $aggregate->conversationId());
    self::assertSame('Reference this facility.', $aggregate->body());

    $references = $aggregate->references();
    self::assertCount(1, $references);
    self::assertSame('facility', $references[0]->type);
    self::assertSame('fac-1', $references[0]->id);
    self::assertSame('Building A', $references[0]->label);
    self::assertSame('FAC-001', $references[0]->code);
  }

  #[Test]
  public function testSaveReplacesTheReferencesAndBodyOfAnExistingMessage(): void
  {
    $message = $this->makeMessage(
      'Original body.',
      [MessageReference::fromArray(['type' => 'intervention', 'id' => 'int-1', 'label' => null, 'code' => null])],
    );
    $this->repository->append($message);
    $this->entityManager->clear();

    $aggregate = $this->repository->findAggregateById((string) $message->id());
    self::assertNotNull($aggregate);
    $aggregate->edit(
      'Edited body with new links.',
      new MentionExtractor(),
      [
        MessageReference::fromArray(['type' => 'non_conformity', 'id' => 'nc-1', 'label' => 'Blocked exit', 'code' => 'NC-9']),
        MessageReference::fromArray(['type' => 'equipment', 'id' => 'eq-2', 'label' => null, 'code' => 'EQ-2']),
      ],
    );

    $saved = $this->repository->save($aggregate);
    self::assertSame('Edited body with new links.', $saved->body);
    self::assertNotNull($saved->editedAt);

    $this->entityManager->clear();
    $reloaded = $this->repository->findById((string) $message->id());
    self::assertNotNull($reloaded);
    self::assertSame('Edited body with new links.', $reloaded->body);
    self::assertEquals([
      ['type' => 'non_conformity', 'id' => 'nc-1', 'label' => 'Blocked exit', 'code' => 'NC-9'],
      ['type' => 'equipment', 'id' => 'eq-2', 'label' => null, 'code' => 'EQ-2'],
    ], $reloaded->references);
  }

  #[Test]
  public function testSaveClearingAllReferencesPersistsAnEmptyReferenceList(): void
  {
    $message = $this->makeMessage(
      'Has one reference.',
      [MessageReference::fromArray(['type' => 'facility', 'id' => 'fac-1', 'label' => null, 'code' => null])],
    );
    $this->repository->append($message);
    $this->entityManager->clear();

    $aggregate = $this->repository->findAggregateById((string) $message->id());
    self::assertNotNull($aggregate);
    // A non-null empty list REPLACES the references wholesale, exercising
    // referencesForStorage()'s empty branch (persisting a NULL column) and
    // referencesFromStorage()'s null branch on the way back.
    $aggregate->edit('No references now.', new MentionExtractor(), []);
    $this->repository->save($aggregate);

    $this->entityManager->clear();
    $reloaded = $this->repository->findById((string) $message->id());
    self::assertNotNull($reloaded);
    self::assertSame([], $reloaded->references);
  }

  #[Test]
  public function testFindByIdReturnsNullForAnUnknownMessage(): void
  {
    self::assertNull($this->repository->findById(self::UNKNOWN_MESSAGE_ID));
  }

  #[Test]
  public function testFindAggregateByIdReturnsNullForAnUnknownMessage(): void
  {
    self::assertNull($this->repository->findAggregateById(self::UNKNOWN_MESSAGE_ID));
  }

  #[Test]
  public function testSaveThrowsWhenTheMessageDoesNotExist(): void
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $message = Message::reconstitute(
      id: MessageId::fromString(self::UNKNOWN_MESSAGE_ID),
      conversationId: self::CONVERSATION_ID,
      organizationId: self::ORG_ID,
      authorMemberId: self::AUTHOR_ID,
      body: 'Never persisted.',
      mentions: [],
      editedAt: null,
      deletedAt: null,
      deletedByMemberId: null,
      createdAt: $now,
      updatedAt: $now,
    );

    $this->expectException(MessagingNotFoundException::class);
    $this->repository->save($message);
  }

  /**
   * @param list<MessageReference> $references the message's structured references
   */
  private function makeMessage(string $body, array $references): Message
  {
    return Message::create(
      MessageId::fromString($this->uuid()),
      self::CONVERSATION_ID,
      self::ORG_ID,
      self::AUTHOR_ID,
      $body,
      new MentionExtractor(),
      null,
      $references,
    );
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

  private function createOrganization(): OrganizationRecord
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORG_ID;
    $organization->name = 'Messaging Message Repository References Test';
    $organization->slug = 'messaging-message-repository-references-test';
    $organization->ownerUserId = '550e8400-e29b-41d4-a716-446655443900';
    $organization->createdByUserId = '550e8400-e29b-41d4-a716-446655443900';
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;
    $this->entityManager->persist($organization);

    return $organization;
  }

  private function createConversation(OrganizationRecord $organization): MessagingConversationRecord
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    $record = new MessagingConversationRecord();
    $record->id = self::CONVERSATION_ID;
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

  private function cleanup(): void
  {
    $connection = $this->entityManager->getConnection();
    $connection->executeStatement(
      'DELETE FROM messaging_messages WHERE organization_id = :organizationId',
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
