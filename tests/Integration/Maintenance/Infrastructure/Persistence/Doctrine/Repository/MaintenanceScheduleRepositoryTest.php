<?php

declare(strict_types=1);

namespace Tests\Integration\Maintenance\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Maintenance\Application\Contract\Schedule\MaintenanceScheduleSnapshot;
use Maintenance\Domain\Exception\MaintenanceNotFoundException;
use Maintenance\Infrastructure\Persistence\Doctrine\Record\MaintenanceScheduleRecord;
use Maintenance\Infrastructure\Persistence\Doctrine\Repository\MaintenanceScheduleRepository;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use RuntimeException;
use Shared\Application\Factory\UuidFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_column;
use function array_map;
use function fwrite;
use function microtime;
use function sort;
use function sprintf;

use const STDERR;

/**
 * Test MaintenanceScheduleRepositoryTest.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MaintenanceScheduleRepository::class)]
final class MaintenanceScheduleRepositoryTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = '990e8400-e29b-41d4-a716-446655490001';

  private const string OTHER_ORGANIZATION_ID = '990e8400-e29b-41d4-a716-446655490002';

  private const string EQUIPMENT_ID_A = '990e8400-e29b-41d4-a716-446655490010';

  private const string EQUIPMENT_ID_B = '990e8400-e29b-41d4-a716-446655490011';

  private const string EQUIPMENT_ID_C = '990e8400-e29b-41d4-a716-446655490012';

  private const string EQUIPMENT_ID_OTHER_ORG = '990e8400-e29b-41d4-a716-446655490013';

  private const string FACILITY_ID_A = '990e8400-e29b-41d4-a716-446655490020';

  private const string FACILITY_ID_B = '990e8400-e29b-41d4-a716-446655490021';

  private EntityManagerInterface $entityManager;

  private MaintenanceScheduleRepository $repository;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    /** @var UuidFactory $uuidFactory */
    $uuidFactory = static::getContainer()->get(UuidFactory::class);

    $this->cleanup();

    $this->repository = new MaintenanceScheduleRepository($this->entityManager, $uuidFactory);

    $this->createOrganization(self::ORGANIZATION_ID);
    $this->createOrganization(self::OTHER_ORGANIZATION_ID);
    $this->entityManager->flush();
    $this->entityManager->clear();
  }

  protected function tearDown(): void
  {
    $this->cleanup();
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testSaveCreatesRowAndFindByIdRoundTrips(): void
  {
    $view = $this->repository->save($this->snapshot(null, self::ORGANIZATION_ID, self::EQUIPMENT_ID_A, 'up_to_date', new DateTimeImmutable('2026-09-01T00:00:00+00:00')));

    self::assertNotSame('', $view->id);
    self::assertSame(self::ORGANIZATION_ID, $view->organizationId);
    self::assertSame(self::EQUIPMENT_ID_A, $view->equipmentId);
    self::assertSame('up_to_date', $view->dueStatus);

    $this->entityManager->clear();

    $found = $this->repository->findById($view->id);
    self::assertNotNull($found);
    self::assertSame($view->id, $found->id);
    self::assertSame(self::EQUIPMENT_ID_A, $found->equipmentId);
    self::assertEquals(new DateTimeImmutable('2026-09-01T00:00:00+00:00'), $found->nextDueAt);
  }

  #[Test]
  public function testFindByIdReturnsNullWhenMissing(): void
  {
    self::assertNull($this->repository->findById('990e8400-e29b-41d4-a716-4466554900ff'));
  }

  #[Test]
  public function testFindByOrganizationAndEquipmentIsScopedToOrganization(): void
  {
    $this->repository->save($this->snapshot(null, self::ORGANIZATION_ID, self::EQUIPMENT_ID_A, 'overdue', null));
    $this->entityManager->clear();

    $found = $this->repository->findByOrganizationAndEquipment(self::ORGANIZATION_ID, self::EQUIPMENT_ID_A);
    self::assertNotNull($found);
    self::assertSame(self::EQUIPMENT_ID_A, $found->equipmentId);

    // The same equipment id queried under a different organization must not leak.
    self::assertNull($this->repository->findByOrganizationAndEquipment(self::OTHER_ORGANIZATION_ID, self::EQUIPMENT_ID_A));
  }

  #[Test]
  public function testSaveWithNullIdReusesExistingRowForOrganizationAndEquipment(): void
  {
    $first = $this->repository->save($this->snapshot(null, self::ORGANIZATION_ID, self::EQUIPMENT_ID_A, 'due_soon', new DateTimeImmutable('2026-09-01T00:00:00+00:00')));
    $this->entityManager->clear();

    $second = $this->repository->save($this->snapshot(null, self::ORGANIZATION_ID, self::EQUIPMENT_ID_A, 'overdue', new DateTimeImmutable('2026-08-01T00:00:00+00:00')));

    self::assertSame($first->id, $second->id);
    self::assertSame('overdue', $second->dueStatus);
  }

  #[Test]
  public function testSaveUpdatesRowById(): void
  {
    $created = $this->repository->save($this->snapshot(null, self::ORGANIZATION_ID, self::EQUIPMENT_ID_A, 'due_soon', new DateTimeImmutable('2026-09-01T00:00:00+00:00')));
    $this->entityManager->clear();

    $updated = $this->repository->save($this->snapshot($created->id, self::ORGANIZATION_ID, self::EQUIPMENT_ID_A, 'overdue', new DateTimeImmutable('2026-07-01T00:00:00+00:00')));

    self::assertSame($created->id, $updated->id);
    self::assertSame('overdue', $updated->dueStatus);
    self::assertEquals(new DateTimeImmutable('2026-07-01T00:00:00+00:00'), $updated->nextDueAt);
  }

  #[Test]
  public function testSaveWithUnknownIdThrowsNotFound(): void
  {
    $this->expectException(MaintenanceNotFoundException::class);

    $this->repository->save($this->snapshot('990e8400-e29b-41d4-a716-4466554900aa', self::ORGANIZATION_ID, self::EQUIPMENT_ID_A, 'overdue', null));
  }

  #[Test]
  public function testRemoveByOrganizationAndEquipmentDeletesRow(): void
  {
    $this->repository->save($this->snapshot(null, self::ORGANIZATION_ID, self::EQUIPMENT_ID_A, 'overdue', null));
    $this->entityManager->clear();

    $this->repository->removeByOrganizationAndEquipment(self::ORGANIZATION_ID, self::EQUIPMENT_ID_A);
    $this->entityManager->clear();

    self::assertNull($this->repository->findByOrganizationAndEquipment(self::ORGANIZATION_ID, self::EQUIPMENT_ID_A));
  }

  #[Test]
  public function testRemoveByOrganizationAndEquipmentIsNoopWhenMissing(): void
  {
    $this->repository->removeByOrganizationAndEquipment(self::ORGANIZATION_ID, self::EQUIPMENT_ID_A);

    self::assertNull($this->repository->findByOrganizationAndEquipment(self::ORGANIZATION_ID, self::EQUIPMENT_ID_A));
  }

  #[Test]
  public function testListIsScopedToOrganizationAndOrdersNullDueDatesLast(): void
  {
    $this->repository->save($this->snapshot(null, self::ORGANIZATION_ID, self::EQUIPMENT_ID_A, 'overdue', new DateTimeImmutable('2026-08-01T00:00:00+00:00')));
    $this->repository->save($this->snapshot(null, self::ORGANIZATION_ID, self::EQUIPMENT_ID_B, 'due_soon', new DateTimeImmutable('2026-09-01T00:00:00+00:00')));
    $this->repository->save($this->snapshot(null, self::ORGANIZATION_ID, self::EQUIPMENT_ID_C, 'unscheduled', null));
    $this->repository->save($this->snapshot(null, self::OTHER_ORGANIZATION_ID, self::EQUIPMENT_ID_OTHER_ORG, 'overdue', new DateTimeImmutable('2026-07-01T00:00:00+00:00')));
    $this->entityManager->clear();

    $page = $this->repository->list(self::ORGANIZATION_ID, null, null, null, null, 1, 100);

    self::assertSame(3, $page->total);
    self::assertCount(3, $page->items);
    self::assertSame(self::EQUIPMENT_ID_A, $page->items[0]->equipmentId);
    self::assertSame(self::EQUIPMENT_ID_B, $page->items[1]->equipmentId);
    self::assertSame(self::EQUIPMENT_ID_C, $page->items[2]->equipmentId);
  }

  #[Test]
  public function testListFiltersByDueStatus(): void
  {
    $this->repository->save($this->snapshot(null, self::ORGANIZATION_ID, self::EQUIPMENT_ID_A, 'overdue', new DateTimeImmutable('2026-08-01T00:00:00+00:00')));
    $this->repository->save($this->snapshot(null, self::ORGANIZATION_ID, self::EQUIPMENT_ID_B, 'up_to_date', new DateTimeImmutable('2026-09-01T00:00:00+00:00')));
    $this->entityManager->clear();

    $page = $this->repository->list(self::ORGANIZATION_ID, null, null, 'overdue', null, 1, 100);

    self::assertSame(1, $page->total);
    self::assertCount(1, $page->items);
    self::assertSame(self::EQUIPMENT_ID_A, $page->items[0]->equipmentId);
  }

  #[Test]
  public function testListPaginatesResults(): void
  {
    $this->repository->save($this->snapshot(null, self::ORGANIZATION_ID, self::EQUIPMENT_ID_A, 'overdue', new DateTimeImmutable('2026-08-01T00:00:00+00:00')));
    $this->repository->save($this->snapshot(null, self::ORGANIZATION_ID, self::EQUIPMENT_ID_B, 'due_soon', new DateTimeImmutable('2026-09-01T00:00:00+00:00')));
    $this->entityManager->clear();

    $page = $this->repository->list(self::ORGANIZATION_ID, null, null, null, null, 1, 1);

    self::assertSame(2, $page->total);
    self::assertCount(1, $page->items);
    self::assertSame(1, $page->page);
    self::assertSame(1, $page->itemsPerPage);
    self::assertSame(self::EQUIPMENT_ID_A, $page->items[0]->equipmentId);
  }

  #[Test]
  public function testListDueForCampaignReturnsOnlyDueScopedAndBeforeCutoff(): void
  {
    // due_soon within cutoff -> included
    $this->repository->save($this->snapshot(null, self::ORGANIZATION_ID, self::EQUIPMENT_ID_A, 'due_soon', new DateTimeImmutable('2026-08-01T00:00:00+00:00')));
    // overdue within cutoff -> included
    $this->repository->save($this->snapshot(null, self::ORGANIZATION_ID, self::EQUIPMENT_ID_B, 'overdue', new DateTimeImmutable('2026-07-15T00:00:00+00:00')));
    // up_to_date -> excluded despite being before cutoff
    $this->repository->save($this->snapshot(null, self::ORGANIZATION_ID, self::EQUIPMENT_ID_C, 'up_to_date', new DateTimeImmutable('2026-07-01T00:00:00+00:00')));
    // due_soon but for another organization -> excluded
    $this->repository->save($this->snapshot(null, self::OTHER_ORGANIZATION_ID, self::EQUIPMENT_ID_OTHER_ORG, 'due_soon', new DateTimeImmutable('2026-07-01T00:00:00+00:00')));
    $this->entityManager->clear();

    $result = $this->repository->listDueForCampaign(self::ORGANIZATION_ID, null, null, new DateTimeImmutable('2026-09-01T00:00:00+00:00'));

    self::assertCount(2, $result);
    // ordered by nextDueAt ASC: B (07-15) before A (08-01)
    self::assertSame(self::EQUIPMENT_ID_B, $result[0]->equipmentId);
    self::assertSame(self::EQUIPMENT_ID_A, $result[1]->equipmentId);
  }

  #[Test]
  public function testPageForSweepReturnsPersistedSchedules(): void
  {
    $this->repository->save($this->snapshot(null, self::ORGANIZATION_ID, self::EQUIPMENT_ID_A, 'overdue', new DateTimeImmutable('2026-08-01T00:00:00+00:00')));
    $this->entityManager->clear();

    $page = $this->repository->pageForSweep(50, 0);

    $equipmentIds = array_map(static fn ($view): string => $view->equipmentId, $page->items);
    self::assertContains(self::EQUIPMENT_ID_A, $equipmentIds);
  }

  #[Test]
  public function testListNarrowsByFacilityEquipmentTypeAndDueBefore(): void
  {
    $this->repository->save($this->snapshot(null, self::ORGANIZATION_ID, self::EQUIPMENT_ID_A, 'overdue', new DateTimeImmutable('2026-08-01T00:00:00+00:00'), self::FACILITY_ID_A));
    $this->repository->save($this->snapshot(null, self::ORGANIZATION_ID, self::EQUIPMENT_ID_B, 'due_soon', new DateTimeImmutable('2026-09-01T00:00:00+00:00'), self::FACILITY_ID_B, 'smoke_detector'));
    $this->repository->save($this->snapshot(null, self::ORGANIZATION_ID, self::EQUIPMENT_ID_C, 'unscheduled', null, self::FACILITY_ID_B));
    $this->entityManager->clear();

    $byFacility = $this->repository->list(self::ORGANIZATION_ID, self::FACILITY_ID_A, null, null, null, 1, 100);
    self::assertSame(1, $byFacility->total);
    self::assertSame(self::EQUIPMENT_ID_A, $byFacility->items[0]->equipmentId);

    $byType = $this->repository->list(self::ORGANIZATION_ID, null, 'smoke_detector', null, null, 1, 100);
    self::assertSame(1, $byType->total);
    self::assertSame(self::EQUIPMENT_ID_B, $byType->items[0]->equipmentId);

    // The null-due row is excluded by the dueBefore arm, which requires a date.
    $byDueBefore = $this->repository->list(self::ORGANIZATION_ID, null, null, null, new DateTimeImmutable('2026-08-15T00:00:00+00:00'), 1, 100);
    self::assertSame(1, $byDueBefore->total);
    self::assertSame(self::EQUIPMENT_ID_A, $byDueBefore->items[0]->equipmentId);
  }

  #[Test]
  public function testListDueForCampaignNarrowsByFacilityAndEquipmentType(): void
  {
    $this->repository->save($this->snapshot(null, self::ORGANIZATION_ID, self::EQUIPMENT_ID_A, 'due_soon', new DateTimeImmutable('2026-08-01T00:00:00+00:00'), self::FACILITY_ID_A));
    $this->repository->save($this->snapshot(null, self::ORGANIZATION_ID, self::EQUIPMENT_ID_B, 'overdue', new DateTimeImmutable('2026-07-15T00:00:00+00:00'), self::FACILITY_ID_B, 'smoke_detector'));
    $this->entityManager->clear();

    $cutoff = new DateTimeImmutable('2026-09-01T00:00:00+00:00');

    $byFacility = $this->repository->listDueForCampaign(self::ORGANIZATION_ID, self::FACILITY_ID_A, null, $cutoff);
    self::assertCount(1, $byFacility);
    self::assertSame(self::EQUIPMENT_ID_A, $byFacility[0]->equipmentId);

    $byType = $this->repository->listDueForCampaign(self::ORGANIZATION_ID, null, 'smoke_detector', $cutoff);
    self::assertCount(1, $byType);
    self::assertSame(self::EQUIPMENT_ID_B, $byType[0]->equipmentId);

    self::assertSame([], $this->repository->listDueForCampaign(self::ORGANIZATION_ID, self::FACILITY_ID_A, 'smoke_detector', $cutoff));
  }

  #[Test]
  public function testFindRefreshesAStaleManagedScheduleAfterAcquiringTheWriterLock(): void
  {
    $created = $this->repository->save($this->snapshot(null, self::ORGANIZATION_ID, self::EQUIPMENT_ID_A, 'overdue', null));
    $this->entityManager->clear();

    // A managed object must not hide the current database state after a writer waits.
    $record = $this->entityManager->find(MaintenanceScheduleRecord::class, $created->id);
    self::assertInstanceOf(MaintenanceScheduleRecord::class, $record);
    $record->organization = null;

    $found = $this->repository->findById($created->id);
    self::assertSame(self::ORGANIZATION_ID, $found?->organizationId);
  }

  #[Test]
  public function dateBoundIncludesTheBoundaryAndAppliesIdenticallyToListExportAndCount(): void
  {
    $boundary = new DateTimeImmutable('2026-06-30T00:00:00+00:00');
    $included = $this->repository->save($this->snapshot(null, self::ORGANIZATION_ID, self::EQUIPMENT_ID_A, 'overdue', $boundary));
    $this->repository->save($this->snapshot(null, self::ORGANIZATION_ID, self::EQUIPMENT_ID_B, 'overdue', $boundary->modify('+1 second')));
    $this->repository->save($this->snapshot(null, self::ORGANIZATION_ID, self::EQUIPMENT_ID_C, 'overdue', null));
    $list = $this->repository->list(self::ORGANIZATION_ID, null, null, 'overdue', $boundary, 1, 100);
    $export = $this->repository->listExportCandidates(self::ORGANIZATION_ID, null, null, 'overdue', $boundary);
    self::assertSame(1, $list->total);
    self::assertSame(1, $this->repository->countForExport(self::ORGANIZATION_ID, null, null, 'overdue', $boundary));
    self::assertSame([$included->id], array_column($export, 'id'));
    self::assertSame($list->items[0]->id, $export[0]->id);
  }

  #[Test]
  public function equalDueDatesAndNullDatesUseIdentifiersForStablePagination(): void
  {
    $due = new DateTimeImmutable('2026-08-01T00:00:00+00:00');
    $a = $this->repository->save($this->snapshot(null, self::ORGANIZATION_ID, self::EQUIPMENT_ID_A, 'overdue', $due));
    $b = $this->repository->save($this->snapshot(null, self::ORGANIZATION_ID, self::EQUIPMENT_ID_B, 'overdue', $due));
    $expected = [$a->id, $b->id];
    sort($expected);
    $first = $this->repository->list(self::ORGANIZATION_ID, null, null, null, null, 1, 1);
    $second = $this->repository->list(self::ORGANIZATION_ID, null, null, null, null, 2, 1);
    self::assertSame($expected, [$first->items[0]->id, $second->items[0]->id]);
    self::assertSame($expected, array_column($this->repository->listDueForCampaign(self::ORGANIZATION_ID, null, null, $due), 'id'));
    foreach ([$a, $b] as $view) {
      $this->repository->save($this->snapshot($view->id, self::ORGANIZATION_ID, $view->equipmentId, 'unscheduled', null));
    }
    self::assertSame($expected, array_column($this->repository->list(self::ORGANIZATION_ID, null, null, null, null, 1, 100)->items, 'id'));
  }

  #[Test]
  public function sweepBatchReadsAndWritesDoNotRetainRecordsOrClearCallerState(): void
  {
    $connection = $this->entityManager->getConnection();
    $connection->executeStatement("INSERT INTO maintenance_schedules (id, organization_id, equipment_id, equipment_type, due_status, created_at, updated_at)
      SELECT md5('memory-schedule-' || n)::uuid::text, :organization, md5('memory-equipment-' || n)::uuid::text, 'fire_extinguisher', 'unscheduled', NOW(), NOW() FROM generate_series(1, 1000) n", ['organization' => self::ORGANIZATION_ID]);
    $organization = $this->entityManager->find(OrganizationRecord::class, self::ORGANIZATION_ID);
    self::assertInstanceOf(OrganizationRecord::class, $organization);
    $organization->name = 'Caller pending change';
    $managedBefore = $this->entityManager->getUnitOfWork()->size();
    for ($offset = 0; $offset < 1000; $offset += 100) {
      $page = $this->repository->pageForSweep(100, $offset);
      $snapshots = [];
      foreach ($page->items as $view) {
        $snapshots[] = $this->snapshot($view->id, $view->organizationId, $view->equipmentId, 'unscheduled', null);
      }
      $this->repository->saveBatch($snapshots);
    }
    self::assertSame($managedBefore, $this->entityManager->getUnitOfWork()->size());
    self::assertTrue($this->entityManager->contains($organization));
    self::assertSame('Caller pending change', $organization->name);
    self::assertSame(1000, $connection->fetchOne('SELECT COUNT(*) FROM maintenance_schedules WHERE organization_id = :organization', ['organization' => self::ORGANIZATION_ID]));
  }

  #[Test]
  public function configuredCampaignLimitCanBeMaterializedThroughTheRealDraftFactory(): void
  {
    $factory = self::getContainer()->get(\Intervention\Application\Port\Inbound\InterventionDraftFactoryPort::class);
    self::assertInstanceOf(\Intervention\Application\Port\Inbound\InterventionDraftFactoryPort::class, $factory);
    $limit = self::getContainer()->getParameter('maintenance.max_campaign_work_items');
    self::assertIsInt($limit);
    $items = [];
    for ($index = 0; $index < $limit; ++$index) {
      $items[] = new \Intervention\Application\Contract\Draft\InterventionDraftWorkItem(action: 'inspection', target: '{"equipmentId":"' . self::EQUIPMENT_ID_A . '"}', required: true);
    }
    $started = microtime(true);
    $created = $factory->create(new \Intervention\Application\Contract\Draft\CreateInterventionDraftRequest(organizationId: self::ORGANIZATION_ID, type: 'inspection_campaign', name: 'Bounded campaign regression', origin: 'maintenance:campaign', workItems: $items, actorUserId: '990e8400-e29b-41d4-a716-446655499000'));
    $elapsed = microtime(true) - $started;
    self::assertSame($limit, $created->workItemsCount);
    fwrite(STDERR, sprintf("\nCampaign boundary: %d work items in %.3f seconds.\n", $limit, $elapsed));
  }

  #[Test]
  public function reminderEnqueueFailureRollsBackTheScheduleMarker(): void
  {
    $created = $this->repository->save($this->snapshot(null, self::ORGANIZATION_ID, self::EQUIPMENT_ID_A, 'overdue', new DateTimeImmutable('2026-01-01')));
    $directory = $this->createStub(\Maintenance\Application\Port\Outbound\Directory\MaintenanceEquipmentDirectoryPort::class);
    $directory->method('listEquipmentPage')->willReturn([]);
    $policies = $this->createStub(\Maintenance\Application\Port\Outbound\Compliance\MaintenanceCompliancePolicyPort::class);
    $policy = new \Maintenance\Application\Contract\Compliance\MaintenanceCompliancePolicy(['fire_extinguisher' => 'P1Y'], 30);
    $policies->method('compliancePolicies')->willReturn([self::ORGANIZATION_ID => $policy]);
    $policies->method('compliancePolicy')->willReturn($policy);
    $clock = $this->createStub(\Shared\Application\Port\Outbound\ClockPort::class);
    $clock->method('now')->willReturn(new DateTimeImmutable('2026-06-01'));
    $locks = new \Maintenance\Infrastructure\Persistence\Doctrine\Lock\MaintenanceScheduleLockAdapter($this->entityManager->getConnection());
    $recompute = new \Maintenance\Domain\Service\MaintenanceScheduleRecomputePolicy();
    $synchronizer = new \Maintenance\Application\Service\MaintenanceScheduleService($this->repository, $directory, $policies, $recompute, $clock, $locks, $this->createStub(\Maintenance\Application\Port\Outbound\Schedule\MaintenanceInspectionHistoryPort::class));
    $events = $this->createStub(\Shared\Application\Port\Outbound\EventDispatcherPort::class);
    $events->method('dispatch')->willThrowException(new RuntimeException('Outbox insert failed'));
    $handler = new \Maintenance\Application\UseCase\Command\Sweep\RecomputeMaintenanceSchedules\RecomputeMaintenanceSchedulesHandler($this->repository, $directory, $policies, $recompute, $clock, $synchronizer, $locks, $events);

    try {
      $handler(new \Maintenance\Application\UseCase\Command\Sweep\RecomputeMaintenanceSchedules\RecomputeMaintenanceSchedulesCommand());
      self::fail('An outbox failure must stop this page.');
    } catch (RuntimeException $exception) {
      self::assertSame('Outbox insert failed', $exception->getMessage());
    }
    $found = $this->repository->findById($created->id);
    self::assertNull($found?->remindedFor);
    self::assertNull($found?->lastRemindedAt);
  }

  #[Test]
  public function reminderRequestAndMarkerCommitTogetherAndRollBackAfterALateFailure(): void
  {
    $created = $this->repository->save($this->snapshot(null, self::ORGANIZATION_ID, self::EQUIPMENT_ID_A, 'overdue', new DateTimeImmutable('2026-01-01')));
    $connection = $this->entityManager->getConnection();
    $registry = $this->createStub(\Doctrine\Persistence\ConnectionRegistry::class);
    $registry->method('getConnection')->willReturn($connection);
    $sender = new \Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransportFactory($registry)->createTransport('doctrine://main?queue_name=maintenance_reminder_regression&auto_setup=false', ['use_notify' => false], new \Symfony\Component\Messenger\Transport\Serialization\PhpSerializer());
    self::assertInstanceOf(\Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransport::class, $sender);
    $sender->setup();
    $connection->executeStatement('DELETE FROM messenger_messages WHERE queue_name = ?', ['maintenance_reminder_regression']);
    $ids = self::getContainer()->get(UuidFactory::class);
    self::assertInstanceOf(UuidFactory::class, $ids);
    $events = new \Shared\Infrastructure\Messaging\Outbox\TransactionalEventDispatcher($connection, $sender, $ids, $this->createStub(\Shared\Application\Port\Outbound\CurrentActorPort::class));
    $directory = $this->createStub(\Maintenance\Application\Port\Outbound\Directory\MaintenanceEquipmentDirectoryPort::class);
    $directory->method('listEquipmentPage')->willReturn([]);
    $policies = $this->createStub(\Maintenance\Application\Port\Outbound\Compliance\MaintenanceCompliancePolicyPort::class);
    $policies->method('compliancePolicies')->willReturn([self::ORGANIZATION_ID => new \Maintenance\Application\Contract\Compliance\MaintenanceCompliancePolicy(['fire_extinguisher' => 'P1Y'], 30)]);
    $clock = $this->createStub(\Shared\Application\Port\Outbound\ClockPort::class);
    $clock->method('now')->willReturn(new DateTimeImmutable('2026-06-01'));
    $locks = new \Maintenance\Infrastructure\Persistence\Doctrine\Lock\MaintenanceScheduleLockAdapter($connection);
    $policy = new \Maintenance\Domain\Service\MaintenanceScheduleRecomputePolicy();
    $synchronizer = new \Maintenance\Application\Service\MaintenanceScheduleService($this->repository, $directory, $policies, $policy, $clock, $locks, $this->createStub(\Maintenance\Application\Port\Outbound\Schedule\MaintenanceInspectionHistoryPort::class));
    $schedules = $this->createStub(\Maintenance\Application\Port\Outbound\Schedule\MaintenanceScheduleRepositoryPort::class);
    $schedules->method('pageForSweep')->willReturn(new \Maintenance\Application\Contract\Schedule\MaintenanceSchedulePage([$created], 1, 200, 0));
    $schedules->method('findForEquipment')->willReturnCallback(fn (): array => [self::EQUIPMENT_ID_A => $this->repository->findById($created->id)]);
    $afterSave = static function (): void { throw new RuntimeException('After schedule update'); };
    $schedules->method('saveBatch')->willReturnCallback(function (array $updates) use (&$afterSave): void {
      /** @var list<MaintenanceScheduleSnapshot> $updates */
      $this->repository->saveBatch($updates);
      $afterSave();
    });
    $handler = new \Maintenance\Application\UseCase\Command\Sweep\RecomputeMaintenanceSchedules\RecomputeMaintenanceSchedulesHandler($schedules, $directory, $policies, $policy, $clock, $synchronizer, $locks, $events);

    try {
      $handler(new \Maintenance\Application\UseCase\Command\Sweep\RecomputeMaintenanceSchedules\RecomputeMaintenanceSchedulesCommand());
      self::fail('The late failure must roll back both writes.');
    } catch (RuntimeException $exception) {
      self::assertSame('After schedule update', $exception->getMessage());
    }
    self::assertNull($this->repository->findById($created->id)?->remindedFor);
    self::assertSame(0, $connection->fetchOne('SELECT COUNT(*) FROM messenger_messages WHERE queue_name = ?', ['maintenance_reminder_regression']));
    $afterSave = static function (): void {};
    $handler(new \Maintenance\Application\UseCase\Command\Sweep\RecomputeMaintenanceSchedules\RecomputeMaintenanceSchedulesCommand());
    self::assertEquals($created->nextDueAt, $this->repository->findById($created->id)?->remindedFor);
    $handler(new \Maintenance\Application\UseCase\Command\Sweep\RecomputeMaintenanceSchedules\RecomputeMaintenanceSchedulesCommand());
    self::assertSame(1, $connection->fetchOne('SELECT COUNT(*) FROM messenger_messages WHERE queue_name = ?', ['maintenance_reminder_regression']));
  }

  private function snapshot(
    ?string $id,
    string $organizationId,
    string $equipmentId,
    string $dueStatus,
    ?DateTimeImmutable $nextDueAt,
    ?string $facilityId = null,
    string $equipmentType = 'fire_extinguisher',
  ): MaintenanceScheduleSnapshot {
    return new MaintenanceScheduleSnapshot(
      $id,
      $organizationId,
      $equipmentId,
      $facilityId,
      $equipmentType,
      null,
      null,
      $nextDueAt,
      $dueStatus,
    );
  }

  private function createOrganization(string $id): void
  {
    $organization = new OrganizationRecord();
    $organization->id = $id;
    $organization->name = 'Maintenance Schedule Repository Test ' . $id;
    $organization->slug = 'maintenance-schedule-repository-test-' . $id;
    $organization->ownerUserId = '990e8400-e29b-41d4-a716-446655499000';
    $organization->createdByUserId = '990e8400-e29b-41d4-a716-446655499000';
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;
    $this->entityManager->persist($organization);
  }

  private function cleanup(): void
  {
    $connection = $this->entityManager->getConnection();
    $connection->executeStatement(
      'DELETE FROM maintenance_schedules WHERE organization_id IN (:organizationIds)',
      ['organizationIds' => [self::ORGANIZATION_ID, self::OTHER_ORGANIZATION_ID]],
      ['organizationIds' => ArrayParameterType::STRING],
    );
    $connection->executeStatement(
      'DELETE FROM organizations WHERE id IN (:organizationIds)',
      ['organizationIds' => [self::ORGANIZATION_ID, self::OTHER_ORGANIZATION_ID]],
      ['organizationIds' => ArrayParameterType::STRING],
    );
    $this->entityManager->clear();
  }
}
