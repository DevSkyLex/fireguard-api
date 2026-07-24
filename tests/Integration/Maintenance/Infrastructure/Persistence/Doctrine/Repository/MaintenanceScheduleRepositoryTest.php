<?php

declare(strict_types=1);

namespace Tests\Integration\Maintenance\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Maintenance\Application\Contract\Schedule\MaintenanceScheduleSnapshot;
use Maintenance\Domain\Exception\MaintenanceNotFoundException;
use Maintenance\Infrastructure\Persistence\Doctrine\Repository\MaintenanceScheduleRepository;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Shared\Application\Factory\UuidFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_map;

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

  private function snapshot(
    ?string $id,
    string $organizationId,
    string $equipmentId,
    string $dueStatus,
    ?DateTimeImmutable $nextDueAt,
  ): MaintenanceScheduleSnapshot {
    return new MaintenanceScheduleSnapshot(
      $id,
      $organizationId,
      $equipmentId,
      null,
      'fire_extinguisher',
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
