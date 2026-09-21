<?php

declare(strict_types=1);

namespace Tests\Integration\Assistant\Infrastructure\Persistence\Doctrine;

use Assistant\Application\Contract\Generation\AssistantGenerationOutcome;
use Assistant\Application\Port\Outbound\{AssistantGenerationDispatcherPort, AssistantRealtimePublisherPort};
use Assistant\Application\Port\Outbound\Organization\AssistantOrganizationSettingsPort;
use Assistant\Application\Service\{AssistantAccessPolicy, AssistantAttemptWriter};
use Assistant\Application\UseCase\Command\Message\ControlAssistantAttempt\{ControlAssistantAttemptCommand, ControlAssistantAttemptHandler};
use Assistant\Application\UseCase\Command\Message\GenerateAssistantReply\GenerateAssistantReplyCommand;
use Assistant\Domain\Exception\{AssistantAttemptConflictException, AssistantThreadNotFoundException};
use Assistant\Domain\Model\Message\AssistantMessage;
use Assistant\Domain\Model\Thread\AssistantThread;
use Assistant\Domain\ValueObject\{AssistantMessageId, AssistantThreadId};
use Assistant\Infrastructure\Adapter\Lock\PostgresAssistantAttemptLockAdapter;
use Assistant\Infrastructure\Persistence\Doctrine\Repository\{AssistantMessageRepository, AssistantThreadRepository};
use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use DateTimeImmutable;
use Doctrine\DBAL\{Connection, DriverManager};
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\ORM\{EntityManager, EntityManagerInterface};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use RuntimeException;
use Shared\Application\Port\Outbound\{ClockPort, EventDispatcherPort, LoggerPort, UuidGeneratorPort};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[SkipDatabaseRollback]
final class AssistantAttemptConcurrencyTest extends KernelTestCase
{
  private const string THREAD = 'bd000000-0000-4000-8000-000000000291';

  private const string MESSAGE = 'bd000000-0000-4000-8000-000000000292';

  private const string QUESTION = 'bd000000-0000-4000-8000-000000000293';

  private const string SECOND = 'bd000000-0000-4000-8000-000000000294';

  private EntityManagerInterface $em;

  private Connection $observer;

  private DateTimeImmutable $now;

  private AssistantRealtimePublisherPort $realtime;

  protected function setUp(): void
  {
    self::bootKernel();
    $em = self::getContainer()->get('doctrine.orm.main_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $em);
    $this->em = $em;
    $url = $_ENV['MAIN_DATABASE_URL'] ?? $_SERVER['MAIN_DATABASE_URL'] ?? null;
    self::assertIsString($url);
    $this->observer = DriverManager::getConnection(['url' => $url]);
    $this->observer->executeStatement('DELETE FROM assistant_threads WHERE id = ?', [self::THREAD]);
    $this->now = new DateTimeImmutable('2026-09-21T10:00:00+00:00');
    $this->realtime = $this->createStub(AssistantRealtimePublisherPort::class);
    new AssistantThreadRepository($em)->save(AssistantThread::start(AssistantThreadId::fromString(self::THREAD), 'org', 'owner', null, $this->now));
    $message = AssistantMessage::pendingReply(AssistantMessageId::fromString(self::MESSAGE), self::THREAD, 'org', $this->now);
    $message->initializeAttempt(self::QUESTION, 0.5, $this->now);
    new AssistantMessageRepository($em)->save($message);
  }

  protected function tearDown(): void
  {
    while ($this->observer->isTransactionActive()) {
      $this->observer->rollBack();
    }
    $this->observer->executeStatement('DELETE FROM assistant_threads WHERE id = ?', [self::THREAD]);
    $this->observer->close();
    parent::tearDown();
  }

  public function testCancellationAndRetryFenceOldWorkersAndDuplicateDeliveries(): void
  {
    $writer = $this->writer($this->em);
    self::assertNotNull($writer->claim($this->generation(self::MESSAGE)));
    self::assertNull($writer->claim($this->generation(self::MESSAGE)));
    self::assertTrue($writer->fragment(self::MESSAGE, self::MESSAGE, 'Partial'));
    $other = new EntityManager($this->observer, $this->em->getConfiguration());
    $handler = $this->handler($other);
    $handler($this->control(false));
    self::assertFalse($writer->fragment(self::MESSAGE, self::MESSAGE, 'late fragment'));
    $writer->finish(self::MESSAGE, self::MESSAGE, new AssistantGenerationOutcome('late completion', 5));
    self::assertSame('Partial', $this->observer->fetchOne('SELECT body FROM assistant_messages WHERE id = ?', [self::MESSAGE]));
    $handler($this->control(true));
    self::assertNull($writer->claim($this->generation(self::MESSAGE)));
    self::assertFalse($writer->fragment(self::MESSAGE, self::MESSAGE, 'still late'));
    self::assertNotNull($writer->claim($this->generation(self::SECOND)));
    self::assertTrue($writer->fragment(self::MESSAGE, self::SECOND, 'New answer'));
    $writer->finish(self::MESSAGE, self::SECOND, new AssistantGenerationOutcome('New answer', 7));
    $row = $this->observer->fetchAssociative('SELECT status, body, attempt_number, attempt_sequence FROM assistant_messages WHERE id = ?', [self::MESSAGE]);
    self::assertSame(['status' => 'complete', 'body' => 'New answer', 'attempt_number' => 2, 'attempt_sequence' => 3], $row);
    self::assertSame(1, $this->observer->fetchOne('SELECT COUNT(*) FROM assistant_messages WHERE thread_id = ?', [self::THREAD]));
  }

  public function testCancellationSerializesAgainstAnInFlightFragmentAndPublishesNoLateFrame(): void
  {
    $writer = $this->writer($this->em);
    $writer->claim($this->generation(self::MESSAGE));
    $hub = $this->createMock(AssistantRealtimePublisherPort::class);
    $hub->expects(self::once())->method('publishGenerationEvent')->willReturnCallback(function (): void {
      $this->observer->executeStatement("SET lock_timeout = '150ms'");
      $other = new EntityManager($this->observer, $this->em->getConfiguration());

      try {
        $this->handler($other)($this->control(false));
        self::fail('HTTP cancellation must wait for the fragment lock.');
      } catch (DriverException $exception) {
        self::assertSame('55P03', $exception->getSQLState());
      }
    });
    $this->realtime = $hub;
    self::assertTrue($this->writer($this->em)->fragment(self::MESSAGE, self::MESSAGE, 'Accepted before cancellation'));
    $this->realtime = $this->createStub(AssistantRealtimePublisherPort::class);
    $other = new EntityManager($this->observer, $this->em->getConfiguration());
    $this->handler($other)($this->control(false));
    // The writer using the strict hub expectation must not emit another frame.
    self::assertFalse($this->writerWithHub($hub)->fragment(self::MESSAGE, self::MESSAGE, 'Late'));
  }

  public function testDeadlineRejectsFinalCompletionAndMakesTheAttemptRetryable(): void
  {
    $writer = $this->writer($this->em);
    $writer->claim($this->generation(self::MESSAGE));
    $this->now = $this->now->modify('+5 minutes');
    $writer->finish(self::MESSAGE, self::MESSAGE, new AssistantGenerationOutcome('Too late', 1));
    self::assertSame('assistant_attempt_expired', $this->observer->fetchOne('SELECT error_code FROM assistant_messages WHERE id = ?', [self::MESSAGE]));
    $this->handler($this->em)($this->control(true));
    self::assertSame($this->now->modify('+5 minutes')->format('Y-m-d H:i:s'), $this->observer->fetchOne('SELECT attempt_expires_at FROM assistant_messages WHERE id = ?', [self::MESSAGE]));
  }

  public function testRetryQueueFailureRollsBackTheAttempt(): void
  {
    $this->handler($this->em)($this->control(false));
    $queue = $this->createStub(AssistantGenerationDispatcherPort::class);
    $queue->method('enqueue')->willThrowException(new RuntimeException('queue unavailable'));

    try {
      $this->handler($this->em, $queue)($this->control(true));
      self::fail('Retry must not be accepted without its durable command.');
    } catch (RuntimeException $error) {
      self::assertSame('queue unavailable', $error->getMessage());
    }
    self::assertSame(['status' => 'cancelled', 'attempt_id' => self::MESSAGE, 'attempt_number' => 1], $this->observer->fetchAssociative('SELECT status, attempt_id, attempt_number FROM assistant_messages WHERE id = ?', [self::MESSAGE]));
  }

  public function testLostRetryResponseCannotQueueAnotherAttempt(): void
  {
    $this->handler($this->em)($this->control(false));
    $queue = $this->createMock(AssistantGenerationDispatcherPort::class);
    $queue->expects(self::once())->method('enqueue')->with('org', self::THREAD, self::QUESTION, self::MESSAGE, null, 0.5, self::SECOND);
    $handler = $this->handler($this->em, $queue);
    $handler($this->control(true));
    $this->expectException(AssistantAttemptConflictException::class);
    $handler($this->control(true));
  }

  public function testAnotherAccountCannotControlThePrivateReply(): void
  {
    $this->expectException(AssistantThreadNotFoundException::class);
    $this->handler($this->em)(new ControlAssistantAttemptCommand('other', 'org', self::THREAD, self::MESSAGE, self::MESSAGE, false));
  }

  private function clock(): ClockPort
  {
    $clock = $this->createStub(ClockPort::class);
    $clock->method('now')->willReturnCallback(fn (): DateTimeImmutable => $this->now);

    return $clock;
  }

  private function writer(EntityManagerInterface $em): AssistantAttemptWriter
  {
    return new AssistantAttemptWriter(new PostgresAssistantAttemptLockAdapter($em), new AssistantMessageRepository($em), new AssistantThreadRepository($em), $this->realtime, $this->clock(), $this->createStub(EventDispatcherPort::class), $this->createStub(LoggerPort::class));
  }

  private function writerWithHub(AssistantRealtimePublisherPort $hub): AssistantAttemptWriter
  {
    $this->realtime = $hub;

    return $this->writer($this->em);
  }

  private function handler(EntityManagerInterface $em, ?AssistantGenerationDispatcherPort $queue = null): ControlAssistantAttemptHandler
  {
    $settings = $this->createStub(AssistantOrganizationSettingsPort::class);
    $settings->method('isEnabledFor')->willReturn(true);
    $ids = $this->createStub(UuidGeneratorPort::class);
    $ids->method('generate')->willReturn(self::SECOND);

    return new ControlAssistantAttemptHandler(new PostgresAssistantAttemptLockAdapter($em), new AssistantMessageRepository($em), new AssistantThreadRepository($em), new AssistantAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $settings), $queue ?? $this->createStub(AssistantGenerationDispatcherPort::class), $this->writer($em), $this->clock(), $ids);
  }

  private function control(bool $retry): ControlAssistantAttemptCommand
  {
    return new ControlAssistantAttemptCommand('owner', 'org', self::THREAD, self::MESSAGE, self::MESSAGE, $retry);
  }

  private function generation(string $attemptId): GenerateAssistantReplyCommand
  {
    return new GenerateAssistantReplyCommand('org', self::THREAD, self::QUESTION, self::MESSAGE, attemptId: $attemptId);
  }
}
