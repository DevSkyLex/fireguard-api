<?php

declare(strict_types=1);

namespace Tests\Integration\Assistant\Infrastructure\Persistence\Doctrine\Repository;

use Assistant\Domain\Model\Message\AssistantMessage;
use Assistant\Domain\Model\Thread\AssistantThread;
use Assistant\Domain\ValueObject\{AssistantMessageId, AssistantThreadId};
use Assistant\Infrastructure\Persistence\Doctrine\Record\AssistantMessageRecord;
use Assistant\Infrastructure\Persistence\Doctrine\Repository\{AssistantMessageRepository, AssistantThreadRepository};
use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Logging\Middleware;
use Doctrine\ORM\{EntityManager, EntityManagerInterface};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_map;
use function array_slice;
use function sprintf;

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

    $reply->markStreaming();
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

  /**
   * Method testCompletedHistoryLoadsOnlyTwentyRowsInOneQueryAndEndsAtTheQueuedQuestion.
   *
   * Verifies a real bounded PostgreSQL read, its hydration limit and its inclusive question boundary.
   *
   * @access public
   * @since unreleased
   *
   * @return void no return value
   */
  #[Test]
  public function testCompletedHistoryLoadsOnlyTwentyRowsInOneQueryAndEndsAtTheQueuedQuestion(): void
  {
    $repository = new AssistantMessageRepository($this->entityManager);
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $ids = [];
    for ($index = 0; $index < 120; ++$index) {
      $ids[] = sprintf('550e8400-e29b-41d4-a716-446655449%03d', 400 + $index);
      $repository->save(AssistantMessage::askUser(AssistantMessageId::fromString($ids[$index]), self::THREAD_ID, self::ORG_ID, 'Question ' . $index, $now->modify('+' . $index . ' seconds')));
    }
    $this->entityManager->clear();
    $sqlLogger = $this->createMock(LoggerInterface::class);
    $sqlLogger->expects(self::once())->method('debug')->with(
      self::anything(),
      self::callback(static function (array $context): bool {
        $sql = $context['sql'];
        self::assertIsString($sql);
        self::assertStringContainsString('LIMIT 20', $sql);

        return true;
      }),
    );
    $configuration = clone $this->entityManager->getConnection()->getConfiguration();
    $configuration->setMiddlewares([...$configuration->getMiddlewares(), new Middleware($sqlLogger)]);
    $connection = DriverManager::getConnection($this->entityManager->getConnection()->getParams(), $configuration);
    $trackedEntityManager = new EntityManager($connection, $this->entityManager->getConfiguration());

    try {
      $results = new AssistantMessageRepository($trackedEntityManager)->listCompletedThroughQuestion(self::THREAD_ID, $ids[100], 20);

      self::assertCount(20, $results);
      self::assertSame(array_slice($ids, 81, 20), array_map(static fn (AssistantMessage $message): string => (string) $message->id(), $results));
      self::assertSame('Question 100', $results[19]->body());
      self::assertCount(20, $trackedEntityManager->getUnitOfWork()->getIdentityMap()[AssistantMessageRecord::class]);
    } finally {
      $trackedEntityManager->close();
      $connection->close();
    }
  }

  /**
   * Method testCompletedHistoryRejectsMissingForeignAndNonUserQuestionAnchors.
   *
   * Verifies thread isolation and rejection of invalid generation anchors.
   *
   * @access public
   * @since unreleased
   *
   * @return void no return value
   */
  #[Test]
  public function testCompletedHistoryRejectsMissingForeignAndNonUserQuestionAnchors(): void
  {
    $repository = new AssistantMessageRepository($this->entityManager);
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $repository->save(AssistantMessage::askUser(AssistantMessageId::fromString(self::OTHER_THREAD_MESSAGE_ID), self::OTHER_THREAD_ID, self::ORG_ID, 'Foreign question', $now));
    $reply = AssistantMessage::pendingReply(AssistantMessageId::fromString(self::MESSAGE_ID_1), self::THREAD_ID, self::ORG_ID, $now);
    $reply->markStreaming();
    $reply->markComplete('Earlier answer', 1, $now);
    $repository->save($reply);
    $this->entityManager->clear();

    self::assertSame([], $repository->listCompletedThroughQuestion(self::THREAD_ID, self::OTHER_THREAD_MESSAGE_ID, 20));
    self::assertSame([], $repository->listCompletedThroughQuestion(self::THREAD_ID, self::MESSAGE_ID_2, 20));
    self::assertSame([], $repository->listCompletedThroughQuestion(self::THREAD_ID, self::MESSAGE_ID_1, 20));
    self::assertSame([], $repository->listCompletedThroughQuestion(self::OTHER_THREAD_ID, self::OTHER_THREAD_MESSAGE_ID, 0));
  }

  /**
   * Method testCompletedHistoryKeepsIdentifierTieOrderAndOmitsIncompleteMessagesAndFutureQuestions.
   *
   * Verifies deterministic tie ordering and exclusion before pagination.
   *
   * @access public
   * @since unreleased
   *
   * @return void no return value
   */
  #[Test]
  public function testCompletedHistoryKeepsIdentifierTieOrderAndOmitsIncompleteMessagesAndFutureQuestions(): void
  {
    $repository = new AssistantMessageRepository($this->entityManager);
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $repository->save(AssistantMessage::askUser(AssistantMessageId::fromString(self::MESSAGE_ID_1), self::THREAD_ID, self::ORG_ID, 'Earlier question', $now));
    $repository->save(AssistantMessage::pendingReply(AssistantMessageId::fromString(self::MESSAGE_ID_2), self::THREAD_ID, self::ORG_ID, $now));
    $repository->save(AssistantMessage::askUser(AssistantMessageId::fromString(self::OTHER_THREAD_MESSAGE_ID), self::THREAD_ID, self::ORG_ID, 'Queued question', $now));
    $futureId = '550e8400-e29b-41d4-a716-446655449403';
    $repository->save(AssistantMessage::askUser(AssistantMessageId::fromString($futureId), self::THREAD_ID, self::ORG_ID, 'Future question', $now));
    $this->entityManager->clear();

    $results = $repository->listCompletedThroughQuestion(self::THREAD_ID, self::OTHER_THREAD_MESSAGE_ID, 20);
    self::assertSame([self::MESSAGE_ID_1, self::OTHER_THREAD_MESSAGE_ID], array_map(static fn (AssistantMessage $message): string => (string) $message->id(), $results));
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
