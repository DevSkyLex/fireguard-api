<?php

declare(strict_types=1);

namespace Tests\Performance;

use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Infrastructure\Adapter\Maintenance\EquipmentMaintenanceDirectoryAdapter;
use Import\Application\Port\Outbound\{ImportExecutionPort, ImportJobRepositoryPort};
use Import\Domain\Model\ImportJob\ImportJob;
use Import\Domain\ValueObject\{ImportJobId, ImportKind, ImportRowError};
use Maintenance\Application\Port\Outbound\Compliance\MaintenanceCompliancePolicyPort;
use Maintenance\Application\Port\Outbound\Directory\MaintenanceEquipmentDirectoryPort;
use Maintenance\Application\Port\Outbound\Schedule\{MaintenanceInspectionHistoryPort, MaintenanceScheduleLockPort, MaintenanceScheduleRepositoryPort};
use Maintenance\Application\Service\MaintenanceScheduleService;
use Maintenance\Application\UseCase\Command\Sweep\RecomputeMaintenanceSchedules\{RecomputeMaintenanceSchedulesCommand, RecomputeMaintenanceSchedulesHandler};
use Maintenance\Domain\Service\MaintenanceScheduleRecomputePolicy;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use Shared\Application\Port\Outbound\ClockPort;
use Shared\Infrastructure\Messaging\Outbox\TransactionalEventDispatcher;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_column;
use function file_put_contents;
use function fwrite;
use function hrtime;
use function json_encode;
use function memory_get_peak_usage;
use function memory_get_usage;
use function memory_reset_peak_usage;
use function sort;
use function sprintf;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const STDERR;

/** Native PostgreSQL page and append budgets; no development fixtures or external deliveries. */
#[SkipDatabaseRollback]
final class MaintenanceImportBoundedWorkloadTest extends KernelTestCase
{
  private const string ORG = 'a3100000-0000-4000-8000-000000000001';

  private EntityManagerInterface $em;

  private SqlQueryCounter $counter;

  protected function setUp(): void
  {
    self::bootKernel();
    $em = self::getContainer()->get('doctrine.orm.main_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $em);
    $this->em = $em;
    $counter = self::getContainer()->get(SqlQueryCounter::class);
    self::assertInstanceOf(SqlQueryCounter::class, $counter);
    $this->counter = $counter;
    $this->clean();
    $org = new OrganizationRecord();
    $org->id = self::ORG;
    $org->name = 'Maintenance import benchmark';
    $org->slug = 'maintenance-import-benchmark';
    $org->ownerUserId = self::ORG;
    $org->createdByUserId = self::ORG;
    $org->createdAt = $org->updatedAt = new DateTimeImmutable('2026-01-01');
    $em->persist($org);
    $em->flush();
  }

  protected function tearDown(): void
  {
    $this->clean();
    parent::tearDown();
  }

  public function testTwentyThousandEquipmentSweepUsesBoundedPagesAndPreservesCallerState(): void
  {
    $db = $this->em->getConnection();
    $db->executeStatement("INSERT INTO equipment (id, organization_id, type, status, created_at, updated_at)
      SELECT md5('maintenance-bench-equipment-' || n)::uuid::text, :org, 'fire_extinguisher', 'operational', '2026-01-01', '2026-01-01' FROM generate_series(1, 20000) n", ['org' => self::ORG]);
    $db->executeStatement("INSERT INTO maintenance_schedules (id, organization_id, equipment_id, equipment_type, due_status, created_at, updated_at)
      SELECT md5('maintenance-bench-schedule-' || n)::uuid::text, :org, md5('maintenance-bench-equipment-' || n)::uuid::text, 'fire_extinguisher', 'unscheduled', '2026-01-01', '2026-01-01' FROM generate_series(1, 20000) n", ['org' => self::ORG]);
    $directory = $this->service(MaintenanceEquipmentDirectoryPort::class);
    self::assertInstanceOf(EquipmentMaintenanceDirectoryAdapter::class, $directory);
    $schedules = $this->service(MaintenanceScheduleRepositoryPort::class);
    $policies = $this->service(MaintenanceCompliancePolicyPort::class);
    $locks = $this->service(MaintenanceScheduleLockPort::class);
    $clock = $this->createStub(ClockPort::class);
    $clock->method('now')->willReturn(new DateTimeImmutable('2026-10-02T00:00:00Z'));
    $policy = new MaintenanceScheduleRecomputePolicy();
    $synchronizer = new MaintenanceScheduleService($schedules, $directory, $policies, $policy, $clock, $locks, $this->service(MaintenanceInspectionHistoryPort::class));
    $sweep = new RecomputeMaintenanceSchedulesHandler($schedules, $directory, $policies, $policy, $clock, $synchronizer, $locks, $this->service(TransactionalEventDispatcher::class));
    $org = $this->em->find(OrganizationRecord::class, self::ORG);
    self::assertInstanceOf(OrganizationRecord::class, $org);
    $org->name = 'Pending caller update';
    $managed = $this->em->getUnitOfWork()->size();
    $samples = [];
    for ($run = 0; $run < 3; ++$run) {
      $this->counter->queries = 0;
      memory_reset_peak_usage();
      $memoryStart = memory_get_usage(true);
      $started = hrtime(true);
      $sweep(new RecomputeMaintenanceSchedulesCommand());
      $sample = ['equipment' => 20000, 'elapsedMs' => (hrtime(true) - $started) / 1e6, 'queries' => $this->counter->queries,
        'managedEntities' => $this->em->getUnitOfWork()->size(), 'incrementalPeakMiB' => (memory_get_peak_usage(true) - $memoryStart) / 1048576];
      self::assertSame($managed, $sample['managedEntities']);
      self::assertTrue($this->em->contains($org));
      self::assertSame('Pending caller update', $org->name);
      self::assertGreaterThan(0, $sample['queries'], 'SQL instrumentation must remain attached after a test-kernel reboot.');
      self::assertLessThanOrEqual(2400, $sample['queries'], 'Queries must grow by bounded pages, not by equipment.');
      self::assertLessThanOrEqual(64, $sample['incrementalPeakMiB']);
      $samples[] = $sample;
    }
    self::assertSame(20000, $db->fetchOne('SELECT COUNT(*) FROM maintenance_schedules WHERE organization_id = ?', [self::ORG]));
    self::assertSame('Maintenance import benchmark', $db->fetchOne('SELECT name FROM organizations WHERE id = ?', [self::ORG]), 'The sweep must not flush a caller update.');
    $times = array_column($samples, 'elapsedMs');
    sort($times);
    self::assertLessThanOrEqual(60000, $times[1]);
    file_put_contents(__DIR__ . '/../../var/maintenance-sweep-benchmark.json', json_encode($samples, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
  }

  public function testDryRunReportsAndResumptionHaveLinearStorageCostAtOneFiveAndTenThousandRows(): void
  {
    $jobs = $this->service(ImportJobRepositoryPort::class);
    $execution = $this->service(ImportExecutionPort::class);
    $db = $this->em->getConnection();
    $samples = [];
    foreach ([1000, 5000, 10000] as $volume) {
      $id = ImportJobId::fromString(sprintf('a3100000-0000-4000-8000-%012d', $volume));
      $jobs->save(ImportJob::create($id, self::ORG, ImportKind::EQUIPMENT, 'unused.csv', 'synthetic.csv', self::ORG, true));
      $managed = $this->em->getUnitOfWork()->size();
      $this->counter->queries = 0;
      memory_reset_peak_usage();
      $memoryStart = memory_get_usage(true);
      $started = hrtime(true);
      $execution->claim($id, self::ORG);
      for ($row = 1; $row <= $volume; ++$row) {
        $current = $execution->run($id, self::ORG, static function (ImportJob $job) use ($row): ?string {
          self::assertSame([], $job->errorReport());
          $job->recordRowSuccess(new ImportRowError($row, 'would_create', 'Would create this synthetic item.'));

          return null;
        }, $row);
        self::assertCount(1, $current->errorReport());
      }
      $sample = ['rows' => $volume, 'appendMs' => (hrtime(true) - $started) / 1e6, 'appendQueries' => $this->counter->queries,
        'managedEntities' => $this->em->getUnitOfWork()->size(), 'incrementalPeakMiB' => (memory_get_peak_usage(true) - $memoryStart) / 1048576];
      self::assertSame($managed, $sample['managedEntities']);
      self::assertGreaterThan(0, $sample['appendQueries'], 'SQL instrumentation must remain attached after a test-kernel reboot.');
      self::assertLessThanOrEqual(10 * $volume + 20, $sample['appendQueries']);
      self::assertLessThanOrEqual(32, $sample['incrementalPeakMiB']);
      self::assertLessThanOrEqual(180000, $sample['appendMs']);
      self::assertNull($db->fetchOne('SELECT error_report FROM import_jobs WHERE id = ?', [(string) $id]));
      self::assertSame($volume, $jobs->countReport($id));
      $execution->run($id, self::ORG, static function (ImportJob $job): ?string {
        $job->fail('Synthetic interruption', new DateTimeImmutable());

        return null;
      });
      $execution->release($id, self::ORG);
      $this->counter->queries = 0;
      $resumeStarted = hrtime(true);
      $resumed = $execution->resume($id, static function (): void {});
      self::assertSame([], $resumed->errorReport());
      $confirmed = $jobs->confirmedSimulationRows($id);
      self::assertCount($volume, $confirmed);
      self::assertSame(1, $confirmed[0]);
      self::assertSame($volume, $confirmed[$volume - 1]);
      $sample['resumeQueries'] = $this->counter->queries;
      $sample['resumeMs'] = (hrtime(true) - $resumeStarted) / 1e6;
      self::assertGreaterThan(0, $sample['resumeQueries']);
      self::assertLessThanOrEqual(10, $sample['resumeQueries']);
      self::assertLessThanOrEqual(2000, $sample['resumeMs']);
      $samples[] = $sample;
      fwrite(STDERR, sprintf("\nImport storage: %d rows, %.0fms, %d queries.\n", $volume, $sample['appendMs'], $sample['appendQueries']));
    }
    self::assertLessThanOrEqual($samples[0]['appendQueries'] / 1000 + 0.01, $samples[2]['appendQueries'] / 10000, 'Per-row query cost must stay constant across retained reports.');
    file_put_contents(__DIR__ . '/../../var/import-report-benchmark.json', json_encode($samples, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
  }

  /**
   * @template T of object
   *
   * @param class-string<T> $id public test service
   *
   * @return T resolved service
   */
  private function service(string $id): object
  {
    $service = self::getContainer()->get($id);
    self::assertInstanceOf($id, $service);

    return $service;
  }

  private function clean(): void
  {
    $db = $this->em->getConnection();
    $db->executeStatement('DELETE FROM import_row_reports WHERE import_job_id IN (SELECT id FROM import_jobs WHERE organization_id = ?)', [self::ORG]);
    $db->executeStatement('DELETE FROM import_row_receipts WHERE import_job_id IN (SELECT id FROM import_jobs WHERE organization_id = ?)', [self::ORG]);
    $db->executeStatement('DELETE FROM import_jobs WHERE organization_id = ?', [self::ORG]);
    $db->executeStatement('DELETE FROM maintenance_schedules WHERE organization_id = ?', [self::ORG]);
    $db->executeStatement('DELETE FROM equipment WHERE organization_id = ?', [self::ORG]);
    $db->executeStatement('DELETE FROM organizations WHERE id = ?', [self::ORG]);
    $this->em->clear();
  }
}
