<?php

declare(strict_types=1);

namespace Tests\Integration\Audit\Infrastructure\Adapter\Doctrine;

use Audit\Application\Contract\AuditRecordRequest;
use Audit\Application\Contract\Event\AuditRecordRequestedEvent;
use Audit\Application\UseCase\Command\RecordAuditEvent\{RecordAuditEventCommand, RecordAuditEventHandler, RecordAuditEventResult};
use Audit\Infrastructure\Adapter\Doctrine\AuditRecordingAdapter;
use Audit\Infrastructure\EventSubscriber\AuditRecordRequestedSubscriber;
use Audit\Infrastructure\Persistence\Doctrine\Repository\AuditEventRepository;
use Audit\Infrastructure\Service\{AuditActorResolver, AuditHashService, AuditPiiSanitizer, AuditRecordPayloadFactory};
use DateTimeImmutable;
use Doctrine\DBAL\{Connection, DriverManager};
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\ORM\{EntityManager, EntityManagerInterface};
use Doctrine\Persistence\ConnectionRegistry;
use PHPUnit\Framework\Attributes\Test;
use Shared\Application\Message\CommandMessage;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Application\Port\Outbound\{CurrentActorPort, UuidGeneratorPort};
use Shared\Infrastructure\Messaging\Outbox\{DoctrineIdempotentConsumerAdapter, DurableEventContext, OutboxEvent, TransactionalEventDispatcher};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\{DoctrineTransport, DoctrineTransportFactory};
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;

use function hash;
use function hash_hmac;
use function iterator_to_array;
use function serialize;
use function str_repeat;

/**
 * Class AuditRecordingAdapterTest
 *
 * Exercises producer rollback and auth ledger receipts using PostgreSQL and native Doctrine outboxes on independent connections.
 *
 * @category Integration Test
 */
final class AuditRecordingAdapterTest extends KernelTestCase
{
  // #region Constants
  /**
   * Constant REQUEST
   *
   * Stable receipt identity shared by retries.
   */
  private const string REQUEST = 'bc000000-0000-4000-8000-000000000101';

  /**
   * Constant LEDGER
   *
   * Ledger append identity used to detect a repeated append.
   */
  private const string LEDGER = 'bc000000-0000-4000-8000-000000000102';

  /**
   * Constant RUN
   *
   * Producer business record owned by this regression.
   */
  private const string RUN = 'bc000000-0000-4000-8000-000000000103';

  /**
   * Constant TENANT
   *
   * Isolates this regression's ledger chain from the baseline.
   */
  private const string TENANT = 'audit-request-regression';

  /**
   * Constant QUEUE
   *
   * Isolates requests from application outbox traffic.
   */
  private const string QUEUE = 'fireguard_audit_request_regression';
  // #endregion

  // #region Properties
  /**
   * Property mainProducer
   *
   * Business write and outbox producer connection.
   */
  private Connection $mainProducer;

  /**
   * Property mainObserver
   *
   * Observes committed business writes and main outbox rows.
   */
  private Connection $mainObserver;

  /**
   * Property authProducer
   *
   * Auth-side producer connection.
   */
  private Connection $authProducer;

  /**
   * Property authWorker
   *
   * Owns consumer receipts and ledger appends.
   */
  private Connection $authWorker;

  /**
   * Property authObserver
   *
   * Observes committed ledger and receipt state.
   */
  private Connection $authObserver;

  /**
   * Property mainSender
   *
   * Native main outbox sender.
   */
  private DoctrineTransport $mainSender;

  /**
   * Property mainReceiver
   *
   * Reads committed main outbox requests.
   */
  private DoctrineTransport $mainReceiver;

  /**
   * Property authSender
   *
   * Native auth outbox sender.
   */
  private DoctrineTransport $authSender;

  /**
   * Property ledgerManagers
   *
   * @var list<EntityManager> independent managers closed after each regression
   */
  private array $ledgerManagers = [];
  // #endregion

  // #region Methods
  /**
   * Method setUp
   *
   * Uses the bootstrap's private PostgreSQL clones and independent driver connections.
   *
   * @access protected
   *
   * @return void
   */
  protected function setUp(): void
  {
    self::bootKernel();
    $mainUrl = $_ENV['MAIN_DATABASE_URL'] ?? $_SERVER['MAIN_DATABASE_URL'] ?? null;
    $authUrl = $_ENV['AUTH_DATABASE_URL'] ?? $_SERVER['AUTH_DATABASE_URL'] ?? null;
    self::assertIsString($mainUrl);
    self::assertIsString($authUrl);
    $this->mainProducer = DriverManager::getConnection(['url' => $mainUrl]);
    $this->mainObserver = DriverManager::getConnection(['url' => $mainUrl]);
    $this->authProducer = DriverManager::getConnection(['url' => $authUrl]);
    $this->authWorker = DriverManager::getConnection(['url' => $authUrl]);
    $this->authObserver = DriverManager::getConnection(['url' => $authUrl]);
    $this->mainSender = $this->transport($this->mainProducer, 'main');
    $this->mainReceiver = $this->transport($this->mainObserver, 'main');
    $this->authSender = $this->transport($this->authProducer, 'auth');
    $this->mainSender->setup();
    $this->authSender->setup();
    $this->clearRows();
  }

  /**
   * Method tearDown
   *
   * Removes only rows owned by this regression and closes independent connections.
   *
   * @access protected
   *
   * @return void
   */
  protected function tearDown(): void
  {
    foreach ($this->ledgerManagers as $manager) {
      $manager->close();
    }
    foreach ([$this->mainProducer, $this->mainObserver, $this->authProducer, $this->authWorker, $this->authObserver] as $connection) {
      while ($connection->isTransactionActive()) {
        $connection->rollBack();
      }
    }
    $this->clearRows();
    foreach ([$this->mainProducer, $this->mainObserver, $this->authProducer, $this->authWorker, $this->authObserver] as $connection) {
      $connection->close();
    }
    parent::tearDown();
  }

  /**
   * Method producerRollbackRemovesTheRequestWithoutTouchingTheAuthLedger
   *
   * A main producer failure rolls back its native outbox and business write together.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function producerRollbackRemovesTheRequestWithoutTouchingTheAuthLedger(): void
  {
    $this->mainProducer->beginTransaction();
    $this->mainProducer->executeStatement(
      'INSERT INTO automation_runs (id, rule_key, organization_id, subject_id, status, created_at) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)',
      [self::RUN, 'audit_request_regression', self::RUN, self::RUN, 'succeeded'],
    );
    $this->producer()->requestRecord($this->request());
    self::assertSame(1, $this->mainSender->getMessageCount());
    self::assertSame(0, $this->mainReceiver->getMessageCount());
    self::assertSame(0, $this->authSender->getMessageCount());
    $this->assertLedgerState(0);
    $this->mainProducer->rollBack();

    self::assertSame(0, $this->mainReceiver->getMessageCount());
    self::assertSame(0, $this->mainObserver->fetchOne('SELECT COUNT(*) FROM automation_runs WHERE id = ?', [self::RUN]));
    $this->assertLedgerState(0);
  }

  /**
   * Method committedRequestsContainSanitizedDataAndDuplicateDeliveryAppendsOnce
   *
   * Verifies durable privacy, a single real ledger append and one auth-owned receipt.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function committedRequestsContainSanitizedDataAndDuplicateDeliveryAppendsOnce(): void
  {
    $event = $this->committedRequest();
    self::assertNull($event->record->actorEmail);
    self::assertSame(hash_hmac('sha256', 'actor@example.test', 'test-audit-salt'), $event->record->actorEmailHash);
    self::assertNull($event->record->ipAddress);
    self::assertSame(hash_hmac('sha256', '192.0.2.17', 'test-audit-salt'), $event->record->ipHash);
    self::assertNull($event->record->metadata['recipient_email']);
    self::assertSame(hash_hmac('sha256', 'recipient@example.test', 'test-audit-salt'), $event->record->metadata['recipient_email_hash']);
    self::assertStringNotContainsString('actor@example.test', serialize($event));
    self::assertStringNotContainsString('recipient@example.test', serialize($event));
    self::assertStringNotContainsString('192.0.2.17', serialize($event));
    $handler = $this->ledgerHandler();
    $commands = $this->createMock(CommandBusPort::class);
    $commands->expects(self::once())->method('dispatch')->willReturnCallback(static function (CommandMessage $command) use ($handler): RecordAuditEventResult {
      self::assertInstanceOf(RecordAuditEventCommand::class, $command);

      return $handler($command);
    });
    $consumer = new AuditRecordRequestedSubscriber($commands, new DoctrineIdempotentConsumerAdapter($this->authWorker));
    $this->mainProducer->beginTransaction();
    $consumer->onRecordRequested($event);
    $this->mainProducer->rollBack();
    $consumer->onRecordRequested($event);

    $this->assertLedgerState(1);
    self::assertSame(0, $this->mainObserver->fetchOne('SELECT COUNT(*) FROM consumed_events WHERE id = ?', [$this->receiptId()]));
    self::assertSame(1, $this->authObserver->fetchOne('SELECT last_sequence FROM audit_event_chains WHERE chain_id = ?', ['tenant:' . self::TENANT]));
    $ledger = $this->authObserver->fetchAssociative('SELECT actor_email, actor_email_hash, ip_address, ip_hash, event_hash FROM audit_events WHERE id = ?', [self::LEDGER]);
    self::assertIsArray($ledger);
    self::assertNull($ledger['actor_email']);
    self::assertSame($event->record->actorEmailHash, $ledger['actor_email_hash']);
    self::assertNull($ledger['ip_address']);
    self::assertSame($event->record->ipHash, $ledger['ip_hash']);
    self::assertSame($ledger['event_hash'], $this->authObserver->fetchOne('SELECT last_hash FROM audit_event_chains WHERE chain_id = ?', ['tenant:' . self::TENANT]));
  }

  /**
   * Method failedAppendRollsBackTheAuthReceiptAndChainBeforeRetry
   *
   * A real repository append failure leaves no receipt or partial chain and remains retryable.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function failedAppendRollsBackTheAuthReceiptAndChainBeforeRetry(): void
  {
    $event = $this->committedRequest();
    $this->authWorker->executeStatement("SET lock_timeout = '150ms'");
    $this->authProducer->beginTransaction();
    $this->authProducer->executeStatement(
      'INSERT INTO audit_event_chains (chain_id, last_hash, last_sequence, updated_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)',
      ['tenant:' . self::TENANT, 'GENESIS', 0],
    );
    $commands = $this->createMock(CommandBusPort::class);
    $commands->expects(self::exactly(2))->method('dispatch')->willReturnCallback(function (CommandMessage $command): RecordAuditEventResult {
      self::assertInstanceOf(RecordAuditEventCommand::class, $command);

      return $this->ledgerHandler()($command);
    });
    $consumer = new AuditRecordRequestedSubscriber($commands, new DoctrineIdempotentConsumerAdapter($this->authWorker));

    try {
      $consumer->onRecordRequested($event);
      self::fail('A failed append must propagate to the durable worker.');
    } catch (DriverException $failure) {
      self::assertSame('55P03', $failure->getSQLState());
    } finally {
      $this->authProducer->rollBack();
    }
    $this->assertLedgerState(0);
    self::assertSame(0, $this->authObserver->fetchOne('SELECT COUNT(*) FROM audit_event_chains WHERE chain_id = ?', ['tenant:' . self::TENANT]));
    $consumer->onRecordRequested($event);
    $consumer->onRecordRequested($event);

    $this->assertLedgerState(1);
    self::assertSame(1, $this->authObserver->fetchOne('SELECT last_sequence FROM audit_event_chains WHERE chain_id = ?', ['tenant:' . self::TENANT]));
  }

  /**
   * Method failedFlushRollsBackTheNewChainAndReceiptBeforeRetry
   *
   * A PostgreSQL ledger insert failure after chain creation rolls back all auth-owned writes.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function failedFlushRollsBackTheNewChainAndReceiptBeforeRetry(): void
  {
    $event = $this->committedRequest();
    $calls = 0;
    $commands = $this->createMock(CommandBusPort::class);
    $commands->expects(self::exactly(2))->method('dispatch')->willReturnCallback(function (CommandMessage $command) use (&$calls): RecordAuditEventResult {
      self::assertInstanceOf(RecordAuditEventCommand::class, $command);
      if (1 === ++$calls) {
        $command = new RecordAuditEventCommand(
          action: $command->action,
          actorType: $command->actorType,
          actorId: $command->actorId,
          actorEmail: $command->actorEmail,
          actorEmailHash: $command->actorEmailHash,
          subjectType: $command->subjectType,
          subjectId: str_repeat('x', 101),
          clientId: $command->clientId,
          tenantId: $command->tenantId,
          organizationId: $command->organizationId,
          ipAddress: $command->ipAddress,
          ipHash: $command->ipHash,
          userAgent: $command->userAgent,
          metadata: $command->metadata,
          occurredAt: $command->occurredAt,
        );
      }

      return $this->ledgerHandler()($command);
    });
    $consumer = new AuditRecordRequestedSubscriber($commands, new DoctrineIdempotentConsumerAdapter($this->authWorker));

    try {
      $consumer->onRecordRequested($event);
      self::fail('A failed ledger flush must propagate to the durable worker.');
    } catch (DriverException $failure) {
      self::assertSame('22001', $failure->getSQLState());
    }
    $this->assertLedgerState(0);
    self::assertSame(0, $this->authObserver->fetchOne('SELECT COUNT(*) FROM audit_event_chains WHERE chain_id = ?', ['tenant:' . self::TENANT]));
    $consumer->onRecordRequested($event);
    $consumer->onRecordRequested($event);

    $this->assertLedgerState(1);
    self::assertSame(1, $this->authObserver->fetchOne('SELECT last_sequence FROM audit_event_chains WHERE chain_id = ?', ['tenant:' . self::TENANT]));
    self::assertSame(self::RUN, $this->authObserver->fetchOne('SELECT subject_id FROM audit_events WHERE id = ?', [self::LEDGER]));
  }

  /**
   * Method producer
   *
   * Shares each native sender with its explicitly owned connection.
   *
   * @access private
   *
   * @return AuditRecordingAdapter real producer adapter
   */
  private function producer(): AuditRecordingAdapter
  {
    $ids = $this->createStub(UuidGeneratorPort::class);
    $ids->method('generate')->willReturn(self::REQUEST);
    $context = new DurableEventContext();
    $actor = $this->createStub(CurrentActorPort::class);

    return new AuditRecordingAdapter(
      $this->authProducer,
      $this->mainProducer,
      new TransactionalEventDispatcher($this->authProducer, $this->authSender, $ids, $actor),
      new TransactionalEventDispatcher($this->mainProducer, $this->mainSender, $ids, $actor),
      $ids,
      $context,
      new AuditRecordPayloadFactory(new AuditPiiSanitizer(false, 'test-audit-salt'), new AuditActorResolver($this->createStub(Security::class), $context), new RequestStack()),
    );
  }

  /**
   * Method request
   *
   * Supplies raw test-only personal data to the producer privacy boundary.
   *
   * @access private
   *
   * @return AuditRecordRequest owner fact
   */
  private function request(): AuditRecordRequest
  {
    return new AuditRecordRequest(
      action: 'facility.archived',
      actorType: 'user',
      actorId: 'actor-id',
      actorEmail: 'actor@example.test',
      subjectType: 'facility',
      subjectId: self::RUN,
      tenantId: self::TENANT,
      organizationId: self::RUN,
      ipAddress: '192.0.2.17',
      userAgent: 'original-agent',
      occurredAt: new DateTimeImmutable('2026-10-11T08:00:00+00:00'),
      emailMetadata: ['recipient_email' => 'recipient@example.test'],
    );
  }

  /**
   * Method committedRequest
   *
   * Reads the producer's committed request through the native transport serializer.
   *
   * @access private
   *
   * @return AuditRecordRequestedEvent durable sanitized request
   */
  private function committedRequest(): AuditRecordRequestedEvent
  {
    $this->mainProducer->transactional(function (): void { $this->producer()->requestRecord($this->request()); });
    $this->assertLedgerState(0);
    $envelopes = iterator_to_array($this->mainReceiver->get());
    self::assertCount(1, $envelopes);
    $message = $envelopes[0]->getMessage();
    self::assertInstanceOf(OutboxEvent::class, $message);
    self::assertInstanceOf(AuditRecordRequestedEvent::class, $message->event);
    self::assertSame(self::REQUEST, $message->event->requestId);
    $this->mainReceiver->ack($envelopes[0]);

    return $message->event;
  }

  /**
   * Method ledgerHandler
   *
   * Uses a fresh manager on the exact auth connection that owns the consumer receipt.
   *
   * @access private
   *
   * @return RecordAuditEventHandler real append handler and repository
   */
  private function ledgerHandler(): RecordAuditEventHandler
  {
    $configured = self::getContainer()->get('doctrine.orm.auth_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $configured);
    $manager = new EntityManager($this->authWorker, $configured->getConfiguration());
    $this->ledgerManagers[] = $manager;
    $ids = $this->createStub(UuidGeneratorPort::class);
    $ids->method('generate')->willReturn(self::LEDGER);

    return new RecordAuditEventHandler($ids, new AuditEventRepository($manager, new AuditHashService()));
  }

  /**
   * Method transport
   *
   * Constructs the native Doctrine transport on a single explicitly named database.
   *
   * @access private
   *
   * @param Connection $connection producer or worker driver connection
   * @param string $owner explicit transport database name
   *
   * @return DoctrineTransport native outbox transport
   */
  private function transport(Connection $connection, string $owner): DoctrineTransport
  {
    $registry = $this->createStub(ConnectionRegistry::class);
    $registry->method('getConnection')->willReturn($connection);
    $transport = new DoctrineTransportFactory($registry)->createTransport(
      'doctrine://' . $owner . '?queue_name=' . self::QUEUE . '&auto_setup=false',
      ['use_notify' => false],
      new PhpSerializer(),
    );
    self::assertInstanceOf(DoctrineTransport::class, $transport);

    return $transport;
  }

  /**
   * Method assertLedgerState
   *
   * Observes the ledger append and auth receipt from an independent committed view.
   *
   * @access private
   *
   * @param int $expected exact committed event and receipt count
   *
   * @return void
   */
  private function assertLedgerState(int $expected): void
  {
    self::assertSame($expected, $this->authObserver->fetchOne('SELECT COUNT(*) FROM audit_events WHERE tenant_id = ?', [self::TENANT]));
    self::assertSame($expected, $this->authObserver->fetchOne('SELECT COUNT(*) FROM consumed_events WHERE id = ?', [$this->receiptId()]));
  }

  /**
   * Method receiptId
   *
   * Uses the stable original delivery and action/subject consumer key.
   *
   * @access private
   *
   * @return string auth receipt primary key
   */
  private function receiptId(): string
  {
    return hash('sha256', self::REQUEST . "\0audit:facility.archived:facility:" . self::RUN);
  }

  /**
   * Method clearRows
   *
   * Cleans only this regression's queue, producer row, tenant chain and receipt.
   *
   * @access private
   *
   * @return void
   */
  private function clearRows(): void
  {
    $this->mainProducer->executeStatement('DELETE FROM messenger_messages WHERE queue_name = ?', [self::QUEUE]);
    $this->authProducer->executeStatement('DELETE FROM messenger_messages WHERE queue_name = ?', [self::QUEUE]);
    $this->mainProducer->executeStatement('DELETE FROM automation_runs WHERE id = ?', [self::RUN]);
    $this->authProducer->executeStatement('DELETE FROM audit_events WHERE tenant_id = ?', [self::TENANT]);
    $this->authProducer->executeStatement('DELETE FROM audit_event_chains WHERE chain_id = ?', ['tenant:' . self::TENANT]);
    $this->authProducer->executeStatement('DELETE FROM consumed_events WHERE id = ?', [$this->receiptId()]);
  }
  // #endregion
}
