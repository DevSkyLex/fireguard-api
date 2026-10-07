<?php

declare(strict_types=1);

namespace Tests\Integration\Intervention\Infrastructure\DataFixtures;

use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Infrastructure\DataFixtures\EquipmentFixtures;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Facility\Infrastructure\DataFixtures\FacilityFixtures;
use Intervention\Domain\ValueObject\{InterventionPriority, InterventionStatus, InterventionType};
use Intervention\Infrastructure\DataFixtures\InterventionFixtures;
use Intervention\Infrastructure\Persistence\Doctrine\Record\{InterventionActivityRecord, InterventionAttachmentRecord, InterventionChangeRecord, InterventionLabelRecord, InterventionNumberCounterRecord, InterventionRecord, InterventionRecurrenceRecord, InterventionRecurrenceRunRecord, InterventionTemplateItemRecord, InterventionTemplateRecord, InterventionWorkItemRecord, PublicationRecord};
use Organization\Infrastructure\DataFixtures\OrganizationFixtures;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Shared\Infrastructure\DataFixtures\SeedUuid;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function count;
use function sprintf;

/**
 * Test InterventionFixturesIntegrationTest.
 *
 * @category Integration Test
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: InterventionFixtures::class)]
final class InterventionFixturesIntegrationTest extends KernelTestCase
{
  // #region Properties
  private EntityManagerInterface $entityManager;
  // #endregion

  // #region Setup
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
  // #endregion

  // #region Tests
  #[Test]
  public function testLoadPersistsTheWholeInterventionGraph(): void
  {
    $this->loadFixtures();

    $interventions = $this->entityManager->getRepository(InterventionRecord::class);

    // The hand-authored interventions plus the generated bulk pool.
    self::assertSame(count(InterventionFixtures::INTERVENTION_SEEDS) + InterventionFixtures::BULK_INTERVENTION_COUNT, $interventions->count([]));
    self::assertSame(count(InterventionFixtures::LABEL_SEEDS), $this->entityManager->getRepository(InterventionLabelRecord::class)->count([]));
    self::assertSame(count(InterventionFixtures::TEMPLATE_SEEDS), $this->entityManager->getRepository(InterventionTemplateRecord::class)->count([]));
    self::assertSame(count(InterventionFixtures::TEMPLATE_ITEM_SEEDS), $this->entityManager->getRepository(InterventionTemplateItemRecord::class)->count([]));
    // One bulk work item per bulk intervention, on top of the hand-authored ones.
    self::assertSame(count(InterventionFixtures::WORK_ITEM_SEEDS) + InterventionFixtures::BULK_INTERVENTION_COUNT, $this->entityManager->getRepository(InterventionWorkItemRecord::class)->count([]));
    self::assertSame(count(InterventionFixtures::CHANGE_SEEDS), $this->entityManager->getRepository(InterventionChangeRecord::class)->count([]));
    self::assertSame(count(InterventionFixtures::PUBLICATION_SEEDS), $this->entityManager->getRepository(PublicationRecord::class)->count([]));
    self::assertSame(count(InterventionFixtures::ATTACHMENT_SEEDS), $this->entityManager->getRepository(InterventionAttachmentRecord::class)->count([]));
    self::assertSame(count(InterventionFixtures::RECURRENCE_SEEDS), $this->entityManager->getRepository(InterventionRecurrenceRecord::class)->count([]));
    self::assertSame(count(InterventionFixtures::RECURRENCE_RUN_SEEDS), $this->entityManager->getRepository(InterventionRecurrenceRunRecord::class)->count([]));
  }

  /**
   * The board, the filters and the status chips are only exercised when every
   * enum case is actually present in the seed.
   */
  #[Test]
  public function testEveryStatusTypeAndPriorityIsRepresented(): void
  {
    $this->loadFixtures();

    $interventions = $this->entityManager->getRepository(InterventionRecord::class);

    foreach (InterventionStatus::cases() as $status) {
      self::assertGreaterThan(0, $interventions->count(['status' => $status->value]), $status->value);
    }

    foreach (InterventionType::cases() as $type) {
      self::assertGreaterThan(0, $interventions->count(['type' => $type->value]), $type->value);
    }

    foreach (InterventionPriority::cases() as $priority) {
      self::assertGreaterThan(0, $interventions->count(['priority' => $priority->value]), $priority->value);
    }

    $workItems = $this->entityManager->getRepository(InterventionWorkItemRecord::class);
    foreach (['planned', 'in_progress', 'completed', 'skipped'] as $workItemStatus) {
      self::assertGreaterThan(0, $workItems->count(['status' => $workItemStatus]), $workItemStatus);
    }

    $changes = $this->entityManager->getRepository(InterventionChangeRecord::class);
    foreach (['proposed', 'applied', 'rejected'] as $changeStatus) {
      self::assertGreaterThan(0, $changes->count(['status' => $changeStatus]), $changeStatus);
    }
  }

  #[Test]
  public function testMaintenanceExamplesKeepExistingIdentitiesAndWaitForActualResults(): void
  {
    $this->loadFixtures();

    $interventions = $this->entityManager->getRepository(InterventionRecord::class);
    for ($index = 0; $index < InterventionFixtures::BULK_INTERVENTION_COUNT; ++$index) {
      $historical = $interventions->find(SeedUuid::from(sprintf('intervention-bulk:%d', $index)));
      self::assertInstanceOf(InterventionRecord::class, $historical);
      self::assertSame(13 + $index, $historical->number);
    }

    /** @var InterventionFixtures $fixtures */
    $fixtures = static::getContainer()->get(InterventionFixtures::class);
    $examples = [
      ['intervention-seed-extinguisher-maintenance', 'maintenance', EquipmentFixtures::EXTINGUISHER_REFERENCE, 'planned'],
      ['intervention-seed-fire-door-repair', 'repair', EquipmentFixtures::BUILDING_FIRE_DOOR_REFERENCE, 'in_progress'],
    ];
    foreach ($examples as [$reference, $action, $equipmentReference, $status]) {
      $intervention = $fixtures->getReference($reference, InterventionRecord::class);
      $equipment = $fixtures->getReference($equipmentReference, EquipmentRecord::class);
      $workItems = $this->entityManager->getRepository(InterventionWorkItemRecord::class)->findBy(['intervention' => $intervention]);
      self::assertCount(1, $workItems);
      $workItem = $workItems[0];
      self::assertSame($action, $workItem->action);
      self::assertSame('/api/equipment/' . $equipment->id, $workItem->target);
      self::assertSame($status, $intervention->status);
      self::assertSame($status, $workItem->status);
      self::assertNull($workItem->executionResult);
      self::assertNull($workItem->resultResource);
      self::assertNull($intervention->closureSnapshot);
      self::assertSame(0, $this->entityManager->getRepository(PublicationRecord::class)->count(['intervention' => $intervention]));

      $activities = $this->entityManager->getRepository(InterventionActivityRecord::class)->findBy(['intervention' => $intervention], ['createdAt' => 'ASC']);
      self::assertSame('created', $activities[0]->event);
      self::assertSame(['from' => 'draft', 'to' => 'planned'], $activities[1]->payload);
      if ('in_progress' === $status) {
        self::assertSame(['from' => 'planned', 'to' => 'in_progress'], $activities[2]->payload);
      }
    }
  }

  /**
   * A skipped work item without a reason is rejected by the workflow gateway;
   * seeding one would be a state the runtime can never produce.
   */
  #[Test]
  public function testSkippedWorkItemsAlwaysCarryTheirReason(): void
  {
    $this->loadFixtures();

    /** @var list<InterventionWorkItemRecord> $skipped */
    $skipped = $this->entityManager->getRepository(InterventionWorkItemRecord::class)->findBy(['status' => 'skipped']);

    self::assertNotEmpty($skipped);
    foreach ($skipped as $workItem) {
      self::assertNotNull($workItem->skipReason);
      self::assertNotSame('', $workItem->skipReason);
    }
  }

  /**
   * The number allocator must never hand out a number an existing row already
   * holds, or the next runtime creation violates the unique
   * `(organization, number)` constraint.
   */
  #[Test]
  public function testTheNumberCounterIsAheadOfEverySeededNumber(): void
  {
    $this->loadFixtures();

    /** @var ?InterventionNumberCounterRecord $counter */
    $counter = $this->entityManager->getRepository(InterventionNumberCounterRecord::class)
      ->find(OrganizationFixtures::ORGANIZATION_ID);

    self::assertNotNull($counter);

    /** @var list<InterventionRecord> $interventions */
    $interventions = $this->entityManager->getRepository(InterventionRecord::class)->findBy([]);
    foreach ($interventions as $intervention) {
      self::assertLessThanOrEqual($counter->lastNumber, $intervention->number);
    }
  }

  #[Test]
  public function testActivityFeedReplaysTheStatusPathOfAPublishedIntervention(): void
  {
    $this->loadFixtures();

    /** @var InterventionFixtures $fixtures */
    $fixtures = static::getContainer()->get(InterventionFixtures::class);
    $published = $fixtures->getReference(InterventionFixtures::PUBLISHED_INTERVENTION_REFERENCE, InterventionRecord::class);

    self::assertSame(InterventionStatus::PUBLISHED->value, $published->status);

    /** @var list<InterventionActivityRecord> $activities */
    $activities = $this->entityManager->getRepository(InterventionActivityRecord::class)
      ->findBy(['intervention' => $published], ['createdAt' => 'ASC']);

    $events = [];
    foreach ($activities as $activity) {
      $events[] = $activity->event;
    }

    // created, then one entry per hop of draft → planned → in_progress →
    // submitted → published, then the authored comments.
    self::assertSame('created', $events[0]);
    self::assertSame(
      ['status_changed', 'status_changed', 'status_changed', 'status_changed'],
      [$events[1], $events[2], $events[3], $events[4]],
    );
    self::assertContains('comment', $events);
  }
  // #endregion

  // #region Helpers
  private function loadFixtures(): void
  {
    /** @var OrganizationFixtures $organizationFixtures */
    $organizationFixtures = static::getContainer()->get(OrganizationFixtures::class);
    /** @var FacilityFixtures $facilityFixtures */
    $facilityFixtures = static::getContainer()->get(FacilityFixtures::class);
    /** @var EquipmentFixtures $equipmentFixtures */
    $equipmentFixtures = static::getContainer()->get(EquipmentFixtures::class);
    /** @var InterventionFixtures $interventionFixtures */
    $interventionFixtures = static::getContainer()->get(InterventionFixtures::class);

    $loader = new Loader();
    $loader->addFixture($organizationFixtures);
    $loader->addFixture($facilityFixtures);
    $loader->addFixture($equipmentFixtures);
    $loader->addFixture($interventionFixtures);

    $executor = new ORMExecutor($this->entityManager, new ORMPurger($this->entityManager));
    // Purge before loading: the test databases carry the seeded baseline, so
    // appending on top of it collides on primary keys and makes the counts
    // above meaningless. DAMA rolls the purge back with the rest of the test.
    $executor->execute($loader->getFixtures(), false);
  }
  // #endregion
}
