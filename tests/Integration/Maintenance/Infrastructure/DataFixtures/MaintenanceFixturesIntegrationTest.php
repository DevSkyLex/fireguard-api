<?php

declare(strict_types=1);

namespace Tests\Integration\Maintenance\Infrastructure\DataFixtures;

use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Infrastructure\DataFixtures\EquipmentFixtures;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Facility\Infrastructure\DataFixtures\FacilityFixtures;
use Maintenance\Domain\ValueObject\MaintenanceDueStatus;
use Maintenance\Infrastructure\DataFixtures\MaintenanceFixtures;
use Maintenance\Infrastructure\Persistence\Doctrine\Record\MaintenanceScheduleRecord;
use Organization\Infrastructure\DataFixtures\OrganizationFixtures;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Shared\Infrastructure\DataFixtures\SeedTimeline;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_count_values;
use function array_keys;
use function array_map;
use function count;

#[CoversClass(className: MaintenanceFixtures::class)]
final class MaintenanceFixturesIntegrationTest extends KernelTestCase
{
  private EntityManagerInterface $entityManager;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;
  }

  protected function tearDown(): void
  {
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testLoadSeedsOneScheduleForEveryTrackedEquipmentWithASpreadOfDueStatuses(): void
  {
    /** @var OrganizationFixtures $organizationFixtures */
    $organizationFixtures = static::getContainer()->get(OrganizationFixtures::class);
    /** @var FacilityFixtures $facilityFixtures */
    $facilityFixtures = static::getContainer()->get(FacilityFixtures::class);
    /** @var EquipmentFixtures $equipmentFixtures */
    $equipmentFixtures = static::getContainer()->get(EquipmentFixtures::class);
    /** @var MaintenanceFixtures $maintenanceFixtures */
    $maintenanceFixtures = static::getContainer()->get(MaintenanceFixtures::class);

    self::assertSame(['maintenance', 'main-seed'], MaintenanceFixtures::getGroups());
    self::assertSame(
      [OrganizationFixtures::class, EquipmentFixtures::class],
      $maintenanceFixtures->getDependencies(),
    );

    $loader = new Loader();
    $loader->addFixture($organizationFixtures);
    $loader->addFixture($facilityFixtures);
    $loader->addFixture($equipmentFixtures);
    $loader->addFixture($maintenanceFixtures);

    $executor = new ORMExecutor($this->entityManager, new ORMPurger($this->entityManager));
    // Purge before loading: the test databases carry the seeded baseline, so
    // appending on top of it collides on primary keys and makes the counts
    // below meaningless. DAMA rolls the purge back with the rest of the test.
    $executor->execute($loader->getFixtures(), false);

    /** @var list<EquipmentRecord> $equipment */
    $equipment = $this->entityManager->getRepository(EquipmentRecord::class)->findAll();
    $trackedEquipmentIds = [];
    foreach ($equipment as $asset) {
      if ('decommissioned' !== $asset->status) {
        $trackedEquipmentIds[$asset->id] = true;
      }
    }
    // At least one seeded asset IS decommissioned, otherwise the "no schedule
    // for decommissioned equipment" rule below would be vacuously true.
    self::assertLessThan(count($equipment), count($trackedEquipmentIds));

    /** @var list<MaintenanceScheduleRecord> $schedules */
    $schedules = $this->entityManager->getRepository(MaintenanceScheduleRecord::class)->findAll();
    self::assertCount(count($trackedEquipmentIds), $schedules);

    $now = SeedTimeline::now();
    foreach ($schedules as $schedule) {
      self::assertArrayHasKey($schedule->equipmentId, $trackedEquipmentIds);
      self::assertSame(OrganizationFixtures::ORGANIZATION_ID, $schedule->organization?->id);
      self::assertNull($schedule->intervalOverride);
      self::assertNotNull($schedule->nextDueAt);

      if (MaintenanceDueStatus::OVERDUE->value === $schedule->dueStatus) {
        self::assertLessThan($now, $schedule->nextDueAt);
      }
      if (MaintenanceDueStatus::UP_TO_DATE->value === $schedule->dueStatus) {
        self::assertGreaterThan($now, $schedule->nextDueAt);
      }
      // Reminder bookkeeping is only written for the due-soon bucket.
      if (MaintenanceDueStatus::DUE_SOON->value === $schedule->dueStatus) {
        self::assertNotNull($schedule->lastRemindedAt);
        self::assertEquals($schedule->nextDueAt, $schedule->remindedFor);
      } else {
        self::assertNull($schedule->lastRemindedAt);
        self::assertNull($schedule->remindedFor);
      }
    }

    // The spread is deliberate: neither a meaningless 0% nor 100% compliance.
    $statusCounts = array_count_values(array_map(
      static fn (MaintenanceScheduleRecord $schedule): string => $schedule->dueStatus,
      $schedules,
    ));
    self::assertArrayHasKey(MaintenanceDueStatus::OVERDUE->value, $statusCounts);
    self::assertArrayHasKey(MaintenanceDueStatus::DUE_SOON->value, $statusCounts);
    self::assertArrayHasKey(MaintenanceDueStatus::UP_TO_DATE->value, $statusCounts);
    self::assertGreaterThan(
      $statusCounts[MaintenanceDueStatus::OVERDUE->value] + $statusCounts[MaintenanceDueStatus::DUE_SOON->value],
      $statusCounts[MaintenanceDueStatus::UP_TO_DATE->value],
    );

    foreach (array_keys($statusCounts) as $status) {
      self::assertNotNull(MaintenanceDueStatus::tryFrom($status));
    }
  }

  #[Test]
  public function testLoadLeavesEquipmentOfAnUntrackedTypeWithoutADueDate(): void
  {
    /** @var OrganizationFixtures $organizationFixtures */
    $organizationFixtures = static::getContainer()->get(OrganizationFixtures::class);
    /** @var FacilityFixtures $facilityFixtures */
    $facilityFixtures = static::getContainer()->get(FacilityFixtures::class);
    /** @var EquipmentFixtures $equipmentFixtures */
    $equipmentFixtures = static::getContainer()->get(EquipmentFixtures::class);
    /** @var MaintenanceFixtures $maintenanceFixtures */
    $maintenanceFixtures = static::getContainer()->get(MaintenanceFixtures::class);

    $loader = new Loader();
    $loader->addFixture($organizationFixtures);
    $loader->addFixture($facilityFixtures);
    $loader->addFixture($equipmentFixtures);

    $executor = new ORMExecutor($this->entityManager, new ORMPurger($this->entityManager));
    $executor->execute($loader->getFixtures(), false);

    // Maintenance is loaded by hand, after the equipment it depends on has
    // been mutated: an equipment type absent from the organization compliance
    // catalog has no periodicity, so no due date can be derived for it.
    $maintenanceFixtures->setReferenceRepository($executor->getReferenceRepository());

    /** @var EquipmentRecord $untracked */
    $untracked = $equipmentFixtures->getReference(EquipmentFixtures::EXTINGUISHER_REFERENCE, EquipmentRecord::class);
    $untracked->type = 'untracked_seed_type';
    $this->entityManager->flush();

    $maintenanceFixtures->load($this->entityManager);

    /** @var list<MaintenanceScheduleRecord> $unscheduled */
    $unscheduled = $this->entityManager->getRepository(MaintenanceScheduleRecord::class)->findBy([
      'equipmentType' => 'untracked_seed_type',
    ]);
    self::assertCount(1, $unscheduled);
    self::assertSame($untracked->id, $unscheduled[0]->equipmentId);
    self::assertNull($unscheduled[0]->lastInspectionClosedAt);
    self::assertNull($unscheduled[0]->nextDueAt);
    self::assertSame(MaintenanceDueStatus::UNSCHEDULED->value, $unscheduled[0]->dueStatus);
  }
}
