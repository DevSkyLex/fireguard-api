<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Messaging\Domain\Exception\MessagingNotFoundException;
use Messaging\Infrastructure\Persistence\Doctrine\Record\{MessagingConversationRecord, MessagingMessageRecord};
use Messaging\Infrastructure\Persistence\Doctrine\Repository\MessagingMessageRepository;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test MessagingMessageRepositoryTest.
 *
 * Covers the identity-map lookups, which need nothing but a stubbed
 * `find()`. In particular a message row whose conversation association is
 * gone must surface as a domain "not found" rather than a TypeError deep in
 * the view mapper. The query-building paths are exercised for real by the
 * integration suite.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MessagingMessageRepository::class)]
final class MessagingMessageRepositoryTest extends TestCase
{
  private const string MESSAGE_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string CONVERSATION_ID = '550e8400-e29b-41d4-a716-446655440002';

  #[Test]
  public function testFindByIdReturnsNullWhenTheRowIsGone(): void
  {
    self::assertNull($this->repository(null)->findById(self::MESSAGE_ID));
  }

  #[Test]
  public function testFindAggregateByIdReturnsNullWhenTheRowIsGone(): void
  {
    self::assertNull($this->repository(null)->findAggregateById(self::MESSAGE_ID));
  }

  #[Test]
  public function testFindByIdMapsTheRecordOntoAView(): void
  {
    $view = $this->repository($this->record())->findById(self::MESSAGE_ID);

    self::assertNotNull($view);
    self::assertSame(self::MESSAGE_ID, $view->id);
    self::assertSame(self::CONVERSATION_ID, $view->conversationId);
    self::assertSame('Hello team', $view->body);
  }

  #[Test]
  public function testFindByIdRejectsARecordWithoutItsConversation(): void
  {
    $record = $this->record();
    $record->conversation = null;

    $this->expectException(MessagingNotFoundException::class);

    $this->repository($record)->findById(self::MESSAGE_ID);
  }

  #[Test]
  public function testFindAggregateByIdRejectsARecordWithoutItsConversation(): void
  {
    $record = $this->record();
    $record->conversation = null;

    $this->expectException(MessagingNotFoundException::class);

    $this->repository($record)->findAggregateById(self::MESSAGE_ID);
  }

  private function repository(?MessagingMessageRecord $record): MessagingMessageRepository
  {
    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('find')->willReturn($record);

    return new MessagingMessageRepository($entityManager);
  }

  private function record(): MessagingMessageRecord
  {
    $conversation = new MessagingConversationRecord();
    $conversation->id = self::CONVERSATION_ID;

    $record = new MessagingMessageRecord();
    $record->id = self::MESSAGE_ID;
    $record->conversation = $conversation;
    $record->organizationId = 'org-1';
    $record->authorMemberId = 'member-1';
    $record->body = 'Hello team';
    $record->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $record->updatedAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return $record;
  }
}
