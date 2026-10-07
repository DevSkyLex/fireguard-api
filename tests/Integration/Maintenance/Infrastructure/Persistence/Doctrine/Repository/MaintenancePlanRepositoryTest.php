<?php

declare(strict_types=1);

namespace Tests\Integration\Maintenance\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\{Connection, DriverManager};
use Doctrine\DBAL\Exception\{DriverException, UniqueConstraintViolationException};
use Doctrine\Persistence\ConnectionRegistry;
use Maintenance\Application\Contract\Plan\{MaintenanceOccurrenceState, MaintenancePlanDetails, MaintenancePlanState};
use Maintenance\Domain\Event\Campaign\MaintenanceCampaignGeneratedEvent;
use Maintenance\Infrastructure\Persistence\Doctrine\Lock\MaintenanceScheduleLockAdapter;
use Maintenance\Infrastructure\Persistence\Doctrine\Repository\MaintenancePlanRepository;
use Maintenance\Presentation\Api\Factory\MaintenancePlanOutputFactory;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use RuntimeException;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\CurrentActorPort;
use Shared\Infrastructure\Messaging\Outbox\TransactionalEventDispatcher;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\{DoctrineTransport, DoctrineTransportFactory};
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;

use const DATE_ATOM;

/** Separate PostgreSQL worker connections prove authority locking and atomic writes. */
final class MaintenancePlanRepositoryTest extends KernelTestCase
{
  private const string ORG = '780e8400-e29b-41d4-a716-446655446001';

  private const string PLAN = '780e8400-e29b-41d4-a716-446655446002';

  private const string OCCURRENCE = '780e8400-e29b-41d4-a716-446655446003';

  private const string SECOND = '780e8400-e29b-41d4-a716-446655446004';

  private const string QUEUE = 'maintenance_plan_atomicity';

  private Connection $a;

  private Connection $b;

  private MaintenancePlanRepository $store;

  private DoctrineTransport $sender;

  private DoctrineTransport $receiver;

  protected function setUp(): void
  {
    self::bootKernel();
    $url = $_ENV['MAIN_DATABASE_URL'] ?? $_SERVER['MAIN_DATABASE_URL'] ?? null;
    self::assertIsString($url);
    $this->a = DriverManager::getConnection(['url' => $url]);
    $this->b = DriverManager::getConnection(['url' => $url]);
    $this->store = new MaintenancePlanRepository($this->a);
    $this->sender = $this->transport($this->a);
    $this->receiver = $this->transport($this->b);
    $this->sender->setup();
    $this->clean();
  }

  protected function tearDown(): void
  {
    foreach ([$this->a, $this->b] as $connection) {
      while ($connection->isTransactionActive()) {
        $connection->rollBack();
      }
    }
    $this->clean();
    $this->a->close();
    $this->b->close();
    parent::tearDown();
  }

  #[Test]
  public function localMonthEndCalendarSurvivesUtcStorageAndDst(): void
  {
    $plan = $this->plan();
    $this->store->save($plan);
    $read = new MaintenancePlanRepository($this->b)->find(self::ORG, self::PLAN);
    self::assertNotNull($read);
    self::assertSame('Europe/Paris', $read->calendarTimezone);
    self::assertSame('2027-01-31T00:00:00+01:00', $read->anchorAt?->format(DATE_ATOM));
    self::assertSame('2027-01-30 23:00:00', $this->b->fetchOne('SELECT anchor_at FROM maintenance_plans WHERE id = ?', [self::PLAN]));
  }

  /**
   * Method openOccurrenceOutputPreservesCalendarAfterUtcReload
   *
   * Reloads real PostgreSQL rows before translating calendar dates and historical instants.
   *
   * @access public
   *
   * @param string $cadenceMode the fixed or historical calendar mode
   * @param string $dueAt the original occurrence date with its explicit offset
   * @param string $storedDueAt the expected UTC timestamp persisted in PostgreSQL
   * @param string $expectedDueAt the expected HTTP timestamp after reloading
   *
   * @return void
   */
  #[Test]
  #[DataProvider('occurrenceCalendarCases')]
  public function openOccurrenceOutputPreservesCalendarAfterUtcReload(string $cadenceMode, string $dueAt, string $storedDueAt, string $expectedDueAt): void
  {
    $plan = $this->plan();
    $plan->cadenceMode = $cadenceMode;
    $plan->anchorAt = $plan->nextDueAt = new DateTimeImmutable($dueAt);
    $occurrence = $this->occurrence();
    $occurrence->dueAt = new DateTimeImmutable($dueAt);
    $this->store->save($plan);
    $this->store->saveOccurrence($occurrence);

    $reader = new MaintenancePlanRepository($this->b);
    $persistedPlan = $reader->find(self::ORG, self::PLAN);
    $persistedOccurrence = $reader->openOccurrence(self::ORG, self::PLAN);
    self::assertNotNull($persistedPlan);
    self::assertNotNull($persistedOccurrence);
    self::assertSame('Europe/Paris', $persistedPlan->calendarTimezone);
    self::assertSame('UTC', $persistedOccurrence->dueAt->getTimezone()->getName());
    self::assertSame($storedDueAt, $this->b->fetchOne('SELECT due_at FROM maintenance_occurrences WHERE id = ?', [self::OCCURRENCE]));

    $output = new MaintenancePlanOutputFactory()->fromDetails(new MaintenancePlanDetails($persistedPlan, $persistedOccurrence));

    self::assertSame($expectedDueAt, $output->openOccurrence?->dueAt);
    if ('fixed' === $cadenceMode) {
      self::assertSame($expectedDueAt, $output->nextDueAt);
    }
  }

  /**
   * Method occurrenceCalendarCases
   *
   * Covers both Paris offsets and the legacy timestamp transport contract.
   *
   * @access public
   *
   * @return iterable<string, array{string, string, string, string}> calendar transport examples
   */
  public static function occurrenceCalendarCases(): iterable
  {
    yield 'fixed winter calendar' => ['fixed', '2027-01-31T00:00:00+01:00', '2027-01-30 23:00:00', '2027-01-31T00:00:00+01:00'];
    yield 'fixed summer calendar' => ['fixed', '2027-03-31T00:00:00+02:00', '2027-03-30 22:00:00', '2027-03-31T00:00:00+02:00'];
    yield 'legacy instant' => ['legacy', '2027-01-31T14:15:00+00:00', '2027-01-31 14:15:00', '2027-01-31T14:15:00+00:00'];
  }

  #[Test]
  public function occurrenceAndOutboxRollBackTogetherAndBecomeVisibleOnlyAfterCommit(): void
  {
    $ids = $this->createStub(UuidFactory::class);
    $ids->method('generateRaw')->willReturn(self::SECOND);
    $dispatcher = new TransactionalEventDispatcher($this->a, $this->sender, $ids, $this->createStub(CurrentActorPort::class));

    try {
      $this->store->synchronized(self::ORG, function () use ($dispatcher): void {
        $this->store->save($this->plan());
        $this->store->saveOccurrence($this->occurrence());
        $dispatcher->dispatch(new MaintenanceCampaignGeneratedEvent(self::ORG, self::SECOND, 1, self::SECOND));
        self::assertSame(0, $this->b->fetchOne('SELECT COUNT(*) FROM maintenance_occurrences WHERE organization_id = ?', [self::ORG]));
        self::assertSame(0, $this->receiver->getMessageCount());

        throw new RuntimeException('Simulated post-reservation failure');
      });
    } catch (RuntimeException $exception) {
      self::assertSame('Simulated post-reservation failure', $exception->getMessage());
    }
    self::assertNull(new MaintenancePlanRepository($this->b)->find(self::ORG, self::PLAN));
    self::assertSame(0, $this->receiver->getMessageCount());
    $this->store->synchronized(self::ORG, function () use ($dispatcher): void {
      $this->store->save($this->plan());
      $this->store->saveOccurrence($this->occurrence());
      $dispatcher->dispatch(new MaintenanceCampaignGeneratedEvent(self::ORG, self::SECOND, 1, self::SECOND));
    });
    self::assertSame(1, $this->receiver->getMessageCount());
    self::assertSame(self::OCCURRENCE, new MaintenancePlanRepository($this->b)->openOccurrence(self::ORG, self::PLAN)?->id);
  }

  #[Test]
  public function historicalWorkersAndThePlansEngineHoldTheSameOrganizationLock(): void
  {
    $legacy = new MaintenanceScheduleLockAdapter($this->a);
    $other = new MaintenancePlanRepository($this->b);
    $this->b->executeStatement("SET lock_timeout = '100ms'");
    $this->a->beginTransaction();
    $legacy->synchronized(self::ORG, self::SECOND, static fn (): bool => true);

    try {
      $other->synchronized(self::ORG, static fn (): bool => true);
      self::fail('The concurrent engine must wait on the legacy worker');
    } catch (DriverException $exception) {
      self::assertSame('55P03', $exception->getSQLState());
    }
    $other->synchronized(self::SECOND, static fn (): bool => true);
    $this->a->commit();
    $other->synchronized(self::ORG, static fn (): bool => true);
  }

  #[Test]
  public function theDatabaseRejectsTwoOpenOccurrencesForOneOperation(): void
  {
    $this->store->save($this->plan());
    $this->store->saveOccurrence($this->occurrence());
    $duplicate = $this->occurrence();
    $duplicate->id = self::SECOND;

    try {
      new MaintenancePlanRepository($this->b)->saveOccurrence($duplicate);
      self::fail('Expected the unique open-occurrence constraint');
    } catch (UniqueConstraintViolationException) {
      self::assertSame(self::OCCURRENCE, $this->store->openOccurrence(self::ORG, self::PLAN)?->id);
    }
  }

  private function plan(): MaintenancePlanState
  {
    $now = new DateTimeImmutable('2026-10-06Z');
    $due = new DateTimeImmutable('2027-01-31T00:00:00', new DateTimeZone('Europe/Paris'));

    return new MaintenancePlanState(self::PLAN, self::ORG, self::SECOND, null, 'fire_extinguisher', 'Monthly control', 'control', 'P1M', 'fixed', $due, $due, true, null, null, null, $now, $now, 'Europe/Paris');
  }

  private function occurrence(): MaintenanceOccurrenceState
  {
    $now = new DateTimeImmutable('2026-10-06Z');

    return new MaintenanceOccurrenceState(self::OCCURRENCE, self::PLAN, self::ORG, new DateTimeImmutable('2027-01-31T00:00:00Z'), 'open', 0, null, null, null, $now, $now);
  }

  private function transport(Connection $connection): DoctrineTransport
  {
    $registry = $this->createStub(ConnectionRegistry::class);
    $registry->method('getConnection')->willReturn($connection);
    $transport = new DoctrineTransportFactory($registry)->createTransport('doctrine://main?queue_name=' . self::QUEUE . '&auto_setup=false', ['use_notify' => false], new PhpSerializer());
    self::assertInstanceOf(DoctrineTransport::class, $transport);

    return $transport;
  }

  private function clean(): void
  {
    $this->a->delete('messenger_messages', ['queue_name' => self::QUEUE]);
    foreach (['maintenance_operation_receipts', 'maintenance_occurrences', 'maintenance_plans', 'maintenance_engines'] as $table) {
      $this->a->delete($table, ['organization_id' => self::ORG]);
    }
  }
}
