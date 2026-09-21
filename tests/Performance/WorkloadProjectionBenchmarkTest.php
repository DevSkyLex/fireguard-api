<?php

declare(strict_types=1);

namespace Tests\Performance;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Infrastructure\Adapter\Statistics\DoctrineInterventionStatisticsGatewayAdapter;
use Intervention\Infrastructure\Adapter\Workload\InterventionWorkloadContributionsAdapter;
use Organization\Application\Contract\Workforce\{OrganizationWorkforceContext, OrganizationWorkforceMember};
use Organization\Application\Port\Inbound\OrganizationWorkforceDirectoryPort;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use Shared\Application\Port\Outbound\ClockPort;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Workload\Application\Contract\Capacity\CapacityWeekView;
use Workload\Application\Port\Outbound\CapacityRepositoryPort;
use Workload\Application\Service\WorkloadProjector;

use function count;
use function file_put_contents;
use function hash;
use function hrtime;
use function json_encode;
use function memory_get_peak_usage;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;

/** Opt-in benchmark on the isolated PostgreSQL test database; never loads development fixtures. */
final class WorkloadProjectionBenchmarkTest extends KernelTestCase
{
  private const string ORG = 'a3000000-0000-4000-8000-000000000001';

  public function testMeasuresCompleteProjectionAndStatistics(): void
  {
    self::bootKernel();
    $em = self::getContainer()->get('doctrine.orm.main_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $em);
    $org = new OrganizationRecord();
    $org->id = self::ORG;
    $org->name = 'Workload benchmark';
    $org->slug = 'workload-benchmark';
    $org->ownerUserId = 'a3000000-0000-4000-8000-000000000002';
    $org->createdByUserId = $org->ownerUserId;
    $org->createdAt = $org->updatedAt = new DateTimeImmutable('2026-09-21T00:00:00+00:00');
    $em->persist($org);
    $em->flush();
    $db = $em->getConnection();
    $db->executeStatement(<<<'SQL'
      INSERT INTO interventions (id, organization_id, type, name, number, status, priority, participants, revision, planned_start_at, due_at, created_at, updated_at)
      SELECT md5('bench-intervention-' || n)::uuid, :org, 'site_setup', 'Bench ' || n, n,
        CASE WHEN n % 5 = 0 THEN 'published' WHEN n % 7 = 0 THEN 'draft' ELSE 'planned' END,
        'normal', '[]', 3, '2026-09-21 00:00:00', '2026-10-02 12:00:00', '2026-09-01', '2026-09-15'
      FROM generate_series(1, 1000) n
      SQL, ['org' => self::ORG]);
    $db->executeStatement(<<<'SQL'
      INSERT INTO intervention_work_items (id, intervention_id, action, assignee_id, source, status, required, revision, remaining_minutes, created_at, updated_at)
      SELECT md5('bench-task-' || n)::uuid, md5('bench-intervention-' || ((n-1) / 20 + 1))::uuid, 'inspect',
        CASE WHEN n % 113 = 0 THEN NULL ELSE md5('bench-member-' || (n % 500))::uuid::text END,
        'planned', CASE WHEN n % 13 = 0 THEN 'completed' ELSE 'planned' END, true, 2,
        CASE WHEN n % 97 = 0 THEN NULL ELSE 120 END, '2026-09-01', '2026-09-15'
      FROM generate_series(1, 20000) n
      SQL);
    $db->executeStatement(<<<'SQL'
      INSERT INTO intervention_time_entries (id, work_item_id, organization_id, member_id, worked_on, minutes, cancelled, revision, created_by, updated_by, created_at, updated_at)
      SELECT md5('bench-time-' || n)::uuid, md5('bench-task-' || n)::uuid, :org, md5('bench-member-' || (n % 500))::uuid,
        '2026-09-21', 30, n % 101 = 0, 4, :actor, :actor, '2026-09-21', '2026-09-21'
      FROM generate_series(1, 20000) n
      SQL, ['org' => self::ORG, 'actor' => $org->ownerUserId]);
    $members = [];
    for ($i = 0; $i < 500; ++$i) {
      $id = $db->fetchOne("SELECT md5('bench-member-' || :n)::uuid::text", ['n' => $i]);
      self::assertIsString($id);
      $members[] = new OrganizationWorkforceMember($id, $id, true);
    }
    $workforce = $this->createStub(OrganizationWorkforceDirectoryPort::class);
    $workforce->method('context')->willReturn(new OrganizationWorkforceContext('Europe/Paris', 'monday'));
    $workforce->method('members')->willReturn($members);
    $capacities = $this->createStub(CapacityRepositoryPort::class);
    $capacities->method('weeks')->willReturn([new CapacityWeekView('week', self::ORG, '2026-01-01', [420, 420, 420, 420, 420, 0, 0])]);
    $capacities->method('exceptions')->willReturn([]);
    $clock = $this->createStub(ClockPort::class);
    $clock->method('now')->willReturn(new DateTimeImmutable('2026-09-21T12:00:00Z'));
    $adapter = new InterventionWorkloadContributionsAdapter($em);
    $projector = new WorkloadProjector($adapter, $workforce, $capacities, $clock);
    $samples = [];
    for ($run = 0; $run < 3; ++$run) {
      $em->clear();
      $started = hrtime(true);
      $tasks = $adapter->tasks(self::ORG, 'Europe/Paris');
      $actuals = $adapter->actuals(self::ORG, '2026-09-21', '2026-10-20');
      $readMs = (hrtime(true) - $started) / 1e6;
      $hydrated = $em->getUnitOfWork()->size();
      $em->clear();
      $started = hrtime(true);
      $snapshot = $projector->project(self::ORG, '2026-09-21', '2026-10-20');
      $projectionMs = (hrtime(true) - $started) / 1e6;
      $started = hrtime(true);
      $statistics = new DoctrineInterventionStatisticsGatewayAdapter($em)->aggregate(self::ORG, new DateTimeImmutable('2026-09-21'));
      $statisticsMs = (hrtime(true) - $started) / 1e6;
      self::assertSame(0, $hydrated, 'Contribution reads must not populate the ORM identity map.');
      self::assertSame(2, $tasks[0]->revision);
      self::assertSame(4, $actuals[0]->revision);
      self::assertCount(500, $snapshot->view->members);
      self::assertSame(1000, $statistics->total);
      self::assertSame(19802, count($actuals));
      self::assertSame('partial', $snapshot->view->completeness);
      $samples[] = ['readMs' => $readMs, 'projectionMs' => $projectionMs, 'statisticsMs' => $statisticsMs, 'managedEntities' => $hydrated,
        'tasks' => count($tasks), 'actuals' => count($actuals), 'fingerprint' => $snapshot->fingerprint,
        'viewHash' => hash('sha256', json_encode($snapshot->view, JSON_THROW_ON_ERROR)), 'peakMiB' => memory_get_peak_usage(true) / 1048576];
    }
    file_put_contents(__DIR__ . '/../../var/workload-benchmark.json', json_encode($samples, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
  }
}
