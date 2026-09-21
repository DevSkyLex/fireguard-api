<?php

declare(strict_types=1);

namespace Tests\Integration\Shared\Infrastructure\Messaging;

use Doctrine\DBAL\{Connection, DriverManager};
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\Persistence\ConnectionRegistry;
use Intervention\Domain\Event\Publication\InterventionPublishedEvent;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Shared\Application\Factory\UuidFactory;
use Shared\Infrastructure\Messaging\Outbox\{DoctrineIdempotentConsumerAdapter, DurableEventContext, OutboxEvent, TransactionalEventDispatcher};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\{DoctrineTransport, DoctrineTransportFactory};
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;

use function hash;
use function iterator_to_array;

/** Real PostgreSQL boundaries, separate worker connections and the native Doctrine sender. */
final class TransactionalOutboxTest extends KernelTestCase
{
  private const string RUN = 'bc000000-0000-4000-8000-000000000081';

  private const string EVENT = 'bc000000-0000-4000-8000-000000000082';

  private const string QUEUE = 'fireguard_outbox_regression';

  private Connection $a;

  private Connection $b;

  private Connection $auth;

  private DoctrineTransport $sender;

  private DoctrineTransport $receiver;

  protected function setUp(): void
  {
    self::bootKernel();
    $mainUrl = $_ENV['MAIN_DATABASE_URL'] ?? $_SERVER['MAIN_DATABASE_URL'] ?? null;
    $authUrl = $_ENV['AUTH_DATABASE_URL'] ?? $_SERVER['AUTH_DATABASE_URL'] ?? null;
    self::assertIsString($mainUrl);
    self::assertIsString($authUrl);
    $this->a = DriverManager::getConnection(['url' => $mainUrl]);
    $this->b = DriverManager::getConnection(['url' => $mainUrl]);
    $this->auth = DriverManager::getConnection(['url' => $authUrl]);
    $this->sender = $this->transport($this->a);
    $this->receiver = $this->transport($this->b);
    $this->sender->setup();
    $this->clearRows();
  }

  protected function tearDown(): void
  {
    foreach ([$this->a, $this->b, $this->auth] as $connection) {
      while ($connection->isTransactionActive()) {
        $connection->rollBack();
      }
    }
    $this->clearRows();
    $this->a->close();
    $this->b->close();
    $this->auth->close();
    parent::tearDown();
  }

  #[Test]
  public function commitsBusinessWritesAndTheEventTogetherAndPreservesTheDeliveryIdentity(): void
  {
    $event = new InterventionPublishedEvent('organization', 'intervention', 'publication');
    $dispatcher = $this->dispatcher();
    $this->a->beginTransaction();
    $this->insertRun();
    $dispatcher->dispatch($event);
    self::assertSame(0, $this->receiver->getMessageCount());
    self::assertSame(0, $this->b->fetchOne('SELECT COUNT(*) FROM automation_runs WHERE id = ?', [self::RUN]));
    $this->a->rollBack();
    self::assertSame(0, $this->receiver->getMessageCount());

    $this->a->transactional(function () use ($dispatcher, $event): void {
      $this->insertRun();
      $dispatcher->dispatch($event);
    });
    self::assertSame(1, $this->receiver->getMessageCount());
    self::assertSame(1, $this->b->fetchOne('SELECT COUNT(*) FROM automation_runs WHERE id = ?', [self::RUN]));
    $envelopes = iterator_to_array($this->receiver->get());
    self::assertCount(1, $envelopes);
    $message = $envelopes[0]->getMessage();
    self::assertInstanceOf(OutboxEvent::class, $message);
    self::assertSame(self::EVENT, $message->id);
    self::assertEquals($event, $message->event);
    $this->receiver->ack($envelopes[0]);
  }

  #[Test]
  public function refusesAnEventWithoutAnOwningTransaction(): void
  {
    $this->expectException(LogicException::class);
    $this->dispatcher()->dispatch(new InterventionPublishedEvent('org', 'intervention', 'publication'));
  }

  #[Test]
  public function rollsBackAConsumerReceiptAndItsBusinessWorkBeforeRetry(): void
  {
    try {
      new DoctrineIdempotentConsumerAdapter($this->a)->consume(self::EVENT, 'test', function (): void {
        $this->insertRun();

        throw new RuntimeException('Interrupted before local confirmation');
      });
      self::fail('The interruption must propagate.');
    } catch (RuntimeException $exception) {
      self::assertSame('Interrupted before local confirmation', $exception->getMessage());
    }
    self::assertSame(0, $this->b->fetchOne('SELECT COUNT(*) FROM automation_runs WHERE id = ?', [self::RUN]));
    self::assertTrue(new DoctrineIdempotentConsumerAdapter($this->b)->consume(self::EVENT, 'test', function (): void {
      $this->insertRun($this->b);
    }));
    self::assertFalse(new DoctrineIdempotentConsumerAdapter($this->a)->consume(self::EVENT, 'test', static function (): void {
      self::fail('A committed consequence must not repeat.');
    }));
  }

  #[Test]
  public function serializesCompetingConsumersWithoutRepeatingTheConsequence(): void
  {
    $this->b->executeStatement("SET lock_timeout = '150ms'");
    new DoctrineIdempotentConsumerAdapter($this->a)->consume(self::EVENT, 'test', function (): void {
      try {
        new DoctrineIdempotentConsumerAdapter($this->b)->consume(self::EVENT, 'test', static function (): void {
          self::fail('The competing consumer must wait for the first commit.');
        });
        self::fail('The competing connection must hit its lock timeout.');
      } catch (DriverException $exception) {
        self::assertSame('55P03', $exception->getSQLState());
      }
      $this->insertRun();
    });
    self::assertFalse(new DoctrineIdempotentConsumerAdapter($this->b)->consume(self::EVENT, 'test', static function (): void {
      self::fail('The first worker already committed.');
    }));
  }

  #[Test]
  public function anAuthReceiptSurvivesAFailedMainDeliveryWithoutCrossDatabaseTransactions(): void
  {
    $context = new DurableEventContext();
    $calls = 0;
    $this->a->beginTransaction();

    try {
      $context->deliver(self::EVENT, function () use (&$calls): void {
        new DoctrineIdempotentConsumerAdapter($this->auth)->consume(self::EVENT, 'test', static function () use (&$calls): void { ++$calls; });

        throw new RuntimeException('A different consumer failed');
      });
    } catch (RuntimeException) {
      $this->a->rollBack();
    }
    self::assertNull($context->eventId());
    $context->deliver(self::EVENT, function () use (&$calls): void {
      new DoctrineIdempotentConsumerAdapter($this->auth)->consume(self::EVENT, 'test', static function () use (&$calls): void { ++$calls; });
    });
    self::assertSame(1, $calls);
    self::assertNull($context->eventId());
  }

  #[Test]
  public function defersInvitationDeliveryUntilItsOwningTransactionCommits(): void
  {
    $queue = new \Organization\Infrastructure\Adapter\Invitation\MessengerInvitationDeliveryQueueAdapter($this->a, $this->sender);
    $this->a->beginTransaction();
    $queue->enqueue(self::EVENT, 'https://app.example.test/accept?token=test-only', 'token-hash');
    self::assertSame(0, $this->receiver->getMessageCount());
    $this->a->rollBack();
    self::assertSame(0, $this->receiver->getMessageCount());
    $this->a->transactional(static fn () => $queue->enqueue(self::EVENT, 'https://app.example.test/accept?token=test-only', 'token-hash'));
    $envelopes = iterator_to_array($this->receiver->get());
    self::assertCount(1, $envelopes);
    $command = $envelopes[0]->getMessage();
    self::assertInstanceOf(\Organization\Application\UseCase\Command\Organization\DeliverOrganizationInvitation\DeliverOrganizationInvitationCommand::class, $command);
    self::assertSame(self::EVENT, $command->invitationId);
    self::assertSame('token-hash', $command->tokenHash);
    $this->receiver->ack($envelopes[0]);
  }

  private function dispatcher(): TransactionalEventDispatcher
  {
    $ids = $this->createStub(UuidFactory::class);
    $ids->method('generateRaw')->willReturn(self::EVENT);

    return new TransactionalEventDispatcher($this->a, $this->sender, $ids, $this->createStub(\Shared\Application\Port\Outbound\CurrentActorPort::class));
  }

  private function transport(Connection $connection): DoctrineTransport
  {
    $registry = $this->createStub(ConnectionRegistry::class);
    $registry->method('getConnection')->willReturn($connection);
    $transport = new DoctrineTransportFactory($registry)->createTransport(
      'doctrine://main?queue_name=' . self::QUEUE . '&auto_setup=false',
      ['use_notify' => false],
      new PhpSerializer(),
    );
    self::assertInstanceOf(DoctrineTransport::class, $transport);

    return $transport;
  }

  private function insertRun(?Connection $connection = null): void
  {
    ($connection ?? $this->a)->executeStatement(
      'INSERT INTO automation_runs (id, rule_key, organization_id, subject_id, status, created_at) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)',
      [self::RUN, 'outbox_regression', self::RUN, self::RUN, 'succeeded'],
    );
  }

  private function clearRows(): void
  {
    $this->a->executeStatement('DELETE FROM messenger_messages WHERE queue_name = ?', [self::QUEUE]);
    $this->a->executeStatement('DELETE FROM automation_runs WHERE id = ?', [self::RUN]);
    foreach ([$this->a, $this->auth] as $connection) {
      $connection->executeStatement('DELETE FROM consumed_events WHERE id = ?', [hash('sha256', self::EVENT . "\0test")]);
    }
  }
}
