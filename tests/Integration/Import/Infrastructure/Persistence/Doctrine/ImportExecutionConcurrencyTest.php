<?php

declare(strict_types=1);

namespace Tests\Integration\Import\Infrastructure\Persistence\Doctrine;

use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use DateTimeImmutable;
use Doctrine\DBAL\{Connection, DriverManager};
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\ORM\{EntityManager, EntityManagerInterface};
use Doctrine\Persistence\ManagerRegistry;
use Equipment\Application\Contract\Provisioning\{ProvisionEquipmentRequest, ProvisionOutcome};
use Equipment\Application\Port\Inbound\EquipmentProvisioningPort;
use Import\Application\Exception\ImportLeaseUnavailable;
use Import\Application\Port\Outbound\{ImportExecutionPort, ImportJobRepositoryPort};
use Import\Domain\Model\ImportJob\ImportJob;
use Import\Domain\ValueObject\{ImportJobId, ImportKind, ImportRowError};
use Import\Infrastructure\Persistence\Doctrine\Execution\PostgresImportExecutionAdapter;
use Import\Infrastructure\Persistence\Doctrine\Repository\ImportJobRepository;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/** Independent PostgreSQL workers, real provisioning and crash boundaries. */
#[SkipDatabaseRollback]
final class ImportExecutionConcurrencyTest extends KernelTestCase
{
  private const string ORG = 'bc000000-0000-4000-8000-000000000091';

  private const string JOB = 'bc000000-0000-4000-8000-000000000092';

  private const string OWNER_A = 'bc000000-0000-4000-8000-000000000093';

  private const string OWNER_B = 'bc000000-0000-4000-8000-000000000094';

  private EntityManagerInterface $em;

  private Connection $a;

  private Connection $b;

  private ImportExecutionPort $execution;

  private ImportExecutionPort $competitor;

  private ImportJobRepositoryPort $jobs;

  protected function setUp(): void
  {
    self::bootKernel();
    $em = self::getContainer()->get('doctrine.orm.main_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $em);
    $this->em = $em;
    $this->a = $this->em->getConnection();
    $url = $_ENV['MAIN_DATABASE_URL'] ?? $_SERVER['MAIN_DATABASE_URL'] ?? null;
    self::assertIsString($url);
    $this->b = DriverManager::getConnection(['url' => $url]);
    $this->clean();
    $org = new OrganizationRecord();
    $org->id = self::ORG;
    $org->name = 'Import execution regression';
    $org->slug = 'import-execution-regression';
    $org->ownerUserId = self::OWNER_A;
    $org->createdByUserId = self::OWNER_A;
    $org->status = 'active';
    $org->isActive = true;
    $org->createdAt = new DateTimeImmutable();
    $org->updatedAt = $org->createdAt;
    $this->em->persist($org);
    $this->em->flush();
    $jobs = self::getContainer()->get(ImportJobRepositoryPort::class);
    self::assertInstanceOf(ImportJobRepositoryPort::class, $jobs);
    $this->jobs = $jobs;
    $this->jobs->save(ImportJob::create($this->id(), self::ORG, ImportKind::EQUIPMENT, 'unused.csv', 'equipment.csv', self::OWNER_A));
    $execution = self::getContainer()->get(ImportExecutionPort::class);
    self::assertInstanceOf(ImportExecutionPort::class, $execution);
    $this->execution = $execution;
    $other = new EntityManager($this->b, $this->em->getConfiguration());
    $registry = self::getContainer()->get(ManagerRegistry::class);
    self::assertInstanceOf(ManagerRegistry::class, $registry);
    $this->competitor = new PostgresImportExecutionAdapter($this->b, $other, $registry, new ImportJobRepository($other));
  }

  protected function tearDown(): void
  {
    foreach ([$this->a, $this->b] as $connection) {
      while ($connection->isTransactionActive()) {
        $connection->rollBack();
      }
    }
    $this->clean();
    $this->b->close();
    parent::tearDown();
  }

  #[Test]
  public function twoWorkersCannotAcquireTheSameLiveLease(): void
  {
    $this->a->beginTransaction();
    self::assertNotNull($this->execution->claim($this->id(), self::OWNER_A));
    $this->b->executeStatement("SET lock_timeout = '150ms'");

    try {
      $this->competitor->claim($this->id(), self::OWNER_B);
      self::fail('The competing acquisition must wait for the first transaction.');
    } catch (DriverException $exception) {
      self::assertSame('55P03', $exception->getSQLState());
    }
    $this->a->commit();
    $this->expectException(ImportLeaseUnavailable::class);
    $this->competitor->claim($this->id(), self::OWNER_B);
  }

  #[Test]
  public function expiredOwnershipIsFencedAndAnOldReleaseCannotClearTheNewLease(): void
  {
    $first = $this->execution->claim($this->id(), self::OWNER_A);
    $this->a->executeStatement("UPDATE import_jobs SET lease_expires_at = clock_timestamp() - INTERVAL '1 second' WHERE id = ?", [self::JOB]);
    $second = $this->competitor->claim($this->id(), self::OWNER_B);
    self::assertEquals($first?->startedAt(), $second?->startedAt());
    $this->execution->release($this->id(), self::OWNER_A);
    self::assertSame(self::OWNER_B, $this->b->fetchOne('SELECT lease_owner FROM import_jobs WHERE id = ?', [self::JOB]));
    $this->expectException(ImportLeaseUnavailable::class);
    $this->execution->run($this->id(), self::OWNER_A, static fn (): ?string => throw new RuntimeException('Stale worker executed'));
  }

  #[Test]
  public function anInterruptionAfterCreationRollsBackThenReplayCommitsExactlyOneResourceAndReceipt(): void
  {
    $this->execution->claim($this->id(), self::OWNER_A);
    $provisioning = self::getContainer()->get(EquipmentProvisioningPort::class);
    self::assertInstanceOf(EquipmentProvisioningPort::class, $provisioning);
    $create = static function (ImportJob $job) use ($provisioning): ?string {
      $result = $provisioning->provision(new ProvisionEquipmentRequest(organizationId: self::ORG, type: 'fire_extinguisher'));
      self::assertSame(ProvisionOutcome::CREATED, $result->outcome);
      $job->recordRowSuccess();

      return $result->resourceId;
    };

    try {
      $this->execution->run($this->id(), self::OWNER_A, static function (ImportJob $job) use ($create): ?string {
        $create($job);

        throw new RuntimeException('Simulated process interruption before receipt');
      }, 1);
      self::fail('The work unit should have rolled back.');
    } catch (RuntimeException $exception) {
      self::assertSame('Simulated process interruption before receipt', $exception->getMessage());
    }
    self::assertSame(0, $this->b->fetchOne('SELECT COUNT(*) FROM equipment WHERE organization_id = ?', [self::ORG]));
    self::assertSame(0, $this->receiptCount());
    self::assertSame(0, $this->jobs->findById($this->id())?->processedRows());
    $this->execution->run($this->id(), self::OWNER_A, $create, 1);
    $this->execution->run($this->id(), self::OWNER_A, static fn (): ?string => throw new RuntimeException('Confirmed row replayed'), 1);
    self::assertSame(1, $this->b->fetchOne('SELECT COUNT(*) FROM equipment WHERE organization_id = ?', [self::ORG]));
    self::assertSame(1, $this->receiptCount());
    self::assertSame(1, $this->jobs->findById($this->id())?->successfulRows());
    self::assertSame(
      $this->b->fetchOne('SELECT id FROM equipment WHERE organization_id = ?', [self::ORG]),
      $this->b->fetchOne('SELECT resource_id FROM import_row_receipts WHERE import_job_id = ?', [self::JOB]),
    );
  }

  #[Test]
  public function aRejectedNestedTransactionDoesNotPoisonTheNextRow(): void
  {
    $this->execution->claim($this->id(), self::OWNER_A);
    $this->execution->run($this->id(), self::OWNER_A, function (ImportJob $job): ?string {
      try {
        $this->em->wrapInTransaction(static fn () => throw new RuntimeException('Rejected row'));
      } catch (RuntimeException) {
        self::assertFalse($this->em->isOpen());
        $job->recordRowError(new ImportRowError(1, 'quota_exceeded', 'Rejected row'));
      }

      return null;
    }, 1);
    self::assertTrue($this->em->isOpen());
    $provisioning = self::getContainer()->get(EquipmentProvisioningPort::class);
    self::assertInstanceOf(EquipmentProvisioningPort::class, $provisioning);
    $this->execution->run($this->id(), self::OWNER_A, static function (ImportJob $job) use ($provisioning): ?string {
      $result = $provisioning->provision(new ProvisionEquipmentRequest(organizationId: self::ORG, type: 'smoke_detector'));
      self::assertSame(ProvisionOutcome::CREATED, $result->outcome);
      $job->recordRowSuccess();

      return $result->resourceId;
    }, 2);
    self::assertSame(2, $this->receiptCount());
    $job = $this->jobs->findById($this->id());
    self::assertInstanceOf(ImportJob::class, $job);
    self::assertSame(1, $job->failedRows());
    self::assertSame(1, $job->successfulRows());
  }

  #[Test]
  public function explicitResumptionKeepsReceiptsAndRollsBackWhenEnqueueFails(): void
  {
    $this->execution->claim($this->id(), self::OWNER_A);
    $this->execution->run($this->id(), self::OWNER_A, static function (ImportJob $job): ?string {
      $job->recordRowSuccess();

      return null;
    }, 1);
    $this->execution->run($this->id(), self::OWNER_A, static function (ImportJob $job): ?string {
      $job->fail('Temporary storage outage', new DateTimeImmutable());

      return null;
    });
    $this->execution->release($this->id(), self::OWNER_A);
    self::assertTrue($this->execution->canResume($this->id()));

    try {
      $this->execution->resume($this->id(), static fn () => throw new RuntimeException('Queue unavailable'));
      self::fail('A failed enqueue must roll back the status transition.');
    } catch (RuntimeException $exception) {
      self::assertSame('Queue unavailable', $exception->getMessage());
    }
    self::assertSame('failed', $this->jobs->findById($this->id())?->status()->value);
    $resumed = $this->execution->resume($this->id(), static function (): void {});
    self::assertSame('pending', $resumed->status()->value);
    self::assertNull($resumed->jobError());
    self::assertSame(1, $resumed->processedRows());
    self::assertSame(1, $this->receiptCount());
    $this->execution->claim($this->id(), self::OWNER_B);
    self::assertFalse($this->execution->canResume($this->id()));
    $this->expectException(ImportLeaseUnavailable::class);
    $this->execution->resume($this->id(), static function (): void {});
  }

  private function id(): ImportJobId
  {
    return ImportJobId::fromString(self::JOB);
  }

  private function receiptCount(): int
  {
    $count = $this->b->fetchOne('SELECT COUNT(*) FROM import_row_receipts WHERE import_job_id = ?', [self::JOB]);
    self::assertIsInt($count);

    return $count;
  }

  private function clean(): void
  {
    $this->a->executeStatement('DELETE FROM import_row_receipts WHERE import_job_id = ?', [self::JOB]);
    $this->a->executeStatement('DELETE FROM import_jobs WHERE id = ?', [self::JOB]);
    $this->a->executeStatement('DELETE FROM equipment WHERE organization_id = ?', [self::ORG]);
    $this->a->executeStatement('DELETE FROM organizations WHERE id = ?', [self::ORG]);
    $this->em->clear();
  }
}
