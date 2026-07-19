<?php

declare(strict_types=1);

namespace Tests\Integration\Assistant\Infrastructure\Persistence\Doctrine\Repository;

use Assistant\Domain\Model\Message\AssistantMessage;
use Assistant\Domain\Model\Thread\AssistantThread;
use Assistant\Domain\ValueObject\{AssistantMessageId, AssistantThreadId};
use Assistant\Infrastructure\Persistence\Doctrine\Repository\{AssistantMessageRepository, AssistantThreadRepository};
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test AssistantMessageRepositoryTest.
 *
 * Exercises the real DQL behind `listByThread()` / `countByThread()`
 * (`IDENTITY(m.thread) = :threadId`, alias `m` — never `member`, a reserved
 * DQL keyword) against a live entity manager, and confirms that transitioning
 * a message through the generation state machine and re-saving REPLACES the
 * same persisted row rather than appending a second one.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AssistantMessageRepository::class)]
final class AssistantMessageRepositoryTest extends KernelTestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655449200';

  private const string MEMBER_ID = '550e8400-e29b-41d4-a716-446655449210';

  private const string THREAD_ID = '550e8400-e29b-41d4-a716-446655449300';

  private const string OTHER_THREAD_ID = '550e8400-e29b-41d4-a716-446655449301';

  private const string MESSAGE_ID_1 = '550e8400-e29b-41d4-a716-446655449400';

  private const string MESSAGE_ID_2 = '550e8400-e29b-41d4-a716-446655449401';

  private const string OTHER_THREAD_MESSAGE_ID = '550e8400-e29b-41d4-a716-446655449402';

  private EntityManagerInterface $entityManager;

  private AssistantThreadRepository $threads;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;
    $this->threads = new AssistantThreadRepository($this->entityManager);

    $this->deleteFixtures();

    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $this->threads->save(AssistantThread::start(AssistantThreadId::fromString(self::THREAD_ID), self::ORG_ID, self::MEMBER_ID, null, $now));
    $this->threads->save(AssistantThread::start(AssistantThreadId::fromString(self::OTHER_THREAD_ID), self::ORG_ID, self::MEMBER_ID, null, $now));
  }

  protected function tearDown(): void
  {
    $this->deleteFixtures();

    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testListByThreadReturnsMessagesInChronologicalOrder(): void
  {
    $repository = new AssistantMessageRepository($this->entityManager);

    $repository->save(AssistantMessage::askUser(
      AssistantMessageId::fromString(self::MESSAGE_ID_1),
      self::THREAD_ID,
      self::ORG_ID,
      'First question',
      new DateTimeImmutable('2026-01-01T00:00:01+00:00'),
    ));

    $repository->save(AssistantMessage::pendingReply(
      AssistantMessageId::fromString(self::MESSAGE_ID_2),
      self::THREAD_ID,
      self::ORG_ID,
      new DateTimeImmutable('2026-01-01T00:00:02+00:00'),
    ));

    $repository->save(AssistantMessage::askUser(
      AssistantMessageId::fromString(self::OTHER_THREAD_MESSAGE_ID),
      self::OTHER_THREAD_ID,
      self::ORG_ID,
      'Not in this thread',
      new DateTimeImmutable('2026-01-01T00:00:03+00:00'),
    ));

    $this->entityManager->clear();

    $results = $repository->listByThread(self::THREAD_ID, 50, 0);

    self::assertCount(2, $results);
    self::assertSame(self::MESSAGE_ID_1, (string) $results[0]->id());
    self::assertSame(self::MESSAGE_ID_2, (string) $results[1]->id());
    self::assertSame(2, $repository->countByThread(self::THREAD_ID));
    self::assertSame(1, $repository->countByThread(self::OTHER_THREAD_ID));
  }

  #[Test]
  public function testMarkCompleteAfterStreamingReplacesTheSamePersistedRow(): void
  {
    $repository = new AssistantMessageRepository($this->entityManager);

    $reply = AssistantMessage::pendingReply(
      AssistantMessageId::fromString(self::MESSAGE_ID_1),
      self::THREAD_ID,
      self::ORG_ID,
      new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    );
    $repository->save($reply);

    $reply->markStreaming(new DateTimeImmutable('2026-01-01T00:00:01+00:00'));
    $repository->save($reply);

    $reply->markComplete('The final, full reply.', 42, new DateTimeImmutable('2026-01-01T00:00:02+00:00'));
    $repository->save($reply);

    $this->entityManager->clear();

    self::assertSame(1, $repository->countByThread(self::THREAD_ID));

    $persisted = $repository->findById(AssistantMessageId::fromString(self::MESSAGE_ID_1));
    self::assertNotNull($persisted);
    self::assertSame('complete', $persisted->status()->value);
    self::assertSame('The final, full reply.', $persisted->body());
    self::assertSame(42, $persisted->tokenCount());
  }

  private function deleteFixtures(): void
  {
    $connection = $this->entityManager->getConnection();
    $connection->executeStatement('DELETE FROM assistant_messages WHERE thread_id IN (:threadId, :otherThreadId)', [
      'threadId' => self::THREAD_ID,
      'otherThreadId' => self::OTHER_THREAD_ID,
    ]);
    $connection->executeStatement('DELETE FROM assistant_threads WHERE id IN (:threadId, :otherThreadId)', [
      'threadId' => self::THREAD_ID,
      'otherThreadId' => self::OTHER_THREAD_ID,
    ]);
  }
}
