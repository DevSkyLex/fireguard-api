<?php

declare(strict_types=1);

namespace Tests\Integration\Import\Infrastructure\Persistence\Doctrine;

use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use DateTimeImmutable;
use Doctrine\DBAL\{Connection, DriverManager};
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\ORM\{EntityManager, EntityManagerInterface};
use Doctrine\Persistence\ConnectionRegistry;
use Import\Application\Port\Outbound\ImportJobQueuePort;
use Import\Application\UseCase\Command\ConfirmImportSimulation\{ConfirmImportSimulationCommand, ConfirmImportSimulationHandler};
use Import\Domain\Model\ImportJob\ImportJob;
use Import\Domain\ValueObject\{ImportJobId, ImportKind};
use Import\Infrastructure\Adapter\Messenger\MessengerImportJobQueueAdapter;
use Import\Infrastructure\Persistence\Doctrine\Lock\PostgresImportConfirmationLockAdapter;
use Import\Infrastructure\Persistence\Doctrine\Repository\ImportJobRepository;
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use RuntimeException;
use Shared\Application\Port\Outbound\{ClockPort, FileStoragePort, UuidGeneratorPort};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\{DoctrineTransport, DoctrineTransportFactory};
use Symfony\Component\Messenger\{Envelope, MessageBusInterface};
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;

#[SkipDatabaseRollback]
final class ImportConfirmationConcurrencyTest extends KernelTestCase
{
  private const string SOURCE = 'bd000000-0000-4000-8000-000000000181';

  private const string REAL = 'bd000000-0000-4000-8000-000000000182';

  private EntityManagerInterface $em;

  private Connection $observer;

  private DoctrineTransport $sender;

  protected function setUp(): void
  {
    self::bootKernel();
    $em = self::getContainer()->get('doctrine.orm.main_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $em);
    $this->em = $em;
    $url = $_ENV['MAIN_DATABASE_URL'] ?? $_SERVER['MAIN_DATABASE_URL'] ?? null;
    self::assertIsString($url);
    $this->observer = DriverManager::getConnection(['url' => $url]);
    $registry = $this->createStub(ConnectionRegistry::class);
    $registry->method('getConnection')->willReturn($em->getConnection());
    $sender = new DoctrineTransportFactory($registry)->createTransport('doctrine://main?queue_name=confirmation_test&auto_setup=false', ['use_notify' => false], new PhpSerializer());
    self::assertInstanceOf(DoctrineTransport::class, $sender);
    $sender->setup();
    $this->sender = $sender;
    $this->clean();
    $source = ImportJob::create(ImportJobId::fromString(self::SOURCE), 'org', ImportKind::FACILITY, 'retained.csv', 'facilities.csv', 'uploader', true);
    $now = new DateTimeImmutable();
    $source->markProcessing($now);
    $source->setTotalRows(1);
    $source->recordRowSuccess();
    $source->complete($now);
    new ImportJobRepository($em)->save($source);
  }

  protected function tearDown(): void
  {
    while ($this->observer->isTransactionActive()) {
      $this->observer->rollBack();
    }
    $this->clean();
    $this->observer->close();
    parent::tearDown();
  }

  public function testConcurrentConfirmationAndLostReplyCreateOneJobAndOneDurableMessage(): void
  {
    $bus = $this->createStub(MessageBusInterface::class);
    $bus->method('dispatch')->willReturnCallback(fn (object $message): Envelope => $this->sender->send(Envelope::wrap($message)));
    $realQueue = new MessengerImportJobQueueAdapter($bus);
    $queue = $this->createMock(ImportJobQueuePort::class);
    $queue->expects(self::once())->method('dispatch')->willReturnCallback(function (string $id, ?string $actor) use ($realQueue): void {
      $realQueue->dispatch($id, $actor);
      self::assertSame(0, $this->jobCount());
      self::assertSame(0, $this->messageCount());
      $this->observer->executeStatement("SET lock_timeout = '150ms'");
      $other = new EntityManager($this->observer, $this->em->getConfiguration());

      try {
        $this->handler($other, $realQueue)(new ConfirmImportSimulationCommand('actor', self::SOURCE));
        self::fail('The competing confirmation must wait for commit.');
      } catch (DriverException $exception) {
        self::assertSame('55P03', $exception->getSQLState());
      }
    });
    $first = $this->handler($this->em, $queue)(new ConfirmImportSimulationCommand('actor', self::SOURCE));
    $other = new EntityManager($this->observer, $this->em->getConfiguration());
    $second = $this->handler($other, $realQueue)(new ConfirmImportSimulationCommand('actor', self::SOURCE));
    self::assertSame($first->importJobId, $second->importJobId);
    self::assertSame(self::REAL, $this->observer->fetchOne('SELECT confirmed_job_id FROM import_jobs WHERE id = ?', [self::SOURCE]));
    self::assertSame(1, $this->messageCount());
  }

  public function testQueueFailureRollsBackTheNewJobAndConfirmation(): void
  {
    $queue = $this->createStub(ImportJobQueuePort::class);
    $queue->method('dispatch')->willThrowException(new RuntimeException('queue unavailable'));

    try {
      $this->handler($this->em, $queue)(new ConfirmImportSimulationCommand('actor', self::SOURCE));
      self::fail('A queue failure must abort confirmation.');
    } catch (RuntimeException $error) {
      self::assertSame('queue unavailable', $error->getMessage());
    }
    self::assertNull($this->observer->fetchOne('SELECT confirmed_job_id FROM import_jobs WHERE id = ?', [self::SOURCE]));
    self::assertSame(0, $this->jobCount());
    self::assertSame(0, $this->messageCount());
  }

  private function clean(): void
  {
    $this->observer->executeStatement('DELETE FROM messenger_messages WHERE body LIKE ?', ['%' . self::REAL . '%']);
    $this->observer->executeStatement('DELETE FROM import_jobs WHERE id IN (?, ?)', [self::SOURCE, self::REAL]);
  }

  private function messageCount(): int
  {
    $count = $this->observer->fetchOne('SELECT COUNT(*) FROM messenger_messages WHERE body LIKE ?', ['%' . self::REAL . '%']);
    self::assertIsInt($count);

    return $count;
  }

  private function jobCount(): int
  {
    $count = $this->observer->fetchOne('SELECT COUNT(*) FROM import_jobs WHERE id = ?', [self::REAL]);
    self::assertIsInt($count);

    return $count;
  }

  private function handler(EntityManagerInterface $em, ImportJobQueuePort $queue): ConfirmImportSimulationHandler
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);
    $files = $this->createStub(FileStoragePort::class);
    $files->method('exists')->willReturn(true);
    $ids = $this->createStub(UuidGeneratorPort::class);
    $ids->method('generate')->willReturn(self::REAL);
    $clock = $this->createStub(ClockPort::class);
    $clock->method('now')->willReturn(new DateTimeImmutable());

    return new ConfirmImportSimulationHandler(new ImportJobRepository($em), $authorization, new PostgresImportConfirmationLockAdapter($em), $queue, $files, $ids, $clock);
  }
}
