<?php

declare(strict_types=1);

namespace Tests\Integration\Facility\Infrastructure\DataFixtures;

use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Infrastructure\DataFixtures\FacilityFixtures;
use Facility\Infrastructure\Persistence\Doctrine\Record\{FacilityAttachmentRecord, FacilityRecord};
use Organization\Infrastructure\DataFixtures\OrganizationFixtures;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function count;

#[CoversClass(className: FacilityFixtures::class)]
final class FacilityFixturesIntegrationTest extends KernelTestCase
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
  public function testLoadPersistsHierarchyWithPublishedReferences(): void
  {
    /** @var OrganizationFixtures $organizationFixtures */
    $organizationFixtures = static::getContainer()->get(OrganizationFixtures::class);
    /** @var FacilityFixtures $facilityFixtures */
    $facilityFixtures = static::getContainer()->get(FacilityFixtures::class);

    $loader = new Loader();
    $loader->addFixture($organizationFixtures);
    $loader->addFixture($facilityFixtures);

    $executor = new ORMExecutor($this->entityManager, new ORMPurger($this->entityManager));
    // Purge before loading: the test databases carry the seeded baseline, so
    // appending on top of it collides on primary keys and makes the counts
    // below meaningless. DAMA rolls the purge back with the rest of the test.
    $executor->execute($loader->getFixtures(), false);

    // 8 Paris nodes + the archived annex + 4 regional sites + their sub-hierarchy.
    self::assertSame(
      9 + count(FacilityFixtures::REGIONAL_SITE_SEEDS) + count(FacilityFixtures::REGIONAL_CHILD_SEEDS),
      $this->entityManager->getRepository(FacilityRecord::class)->count([]),
    );
    self::assertSame(10, $this->entityManager->getRepository(FacilityAttachmentRecord::class)->count([]));

    // Two of those ten are floor plans, one per floor, and each is its floor's
    // primary. Without them the plan viewer, the outline editor and the 3D
    // building view have nothing to render — which is exactly the state the
    // seed was in until these were added, and a bare total would not catch a
    // regression back to it.
    $floorPlans = $this->entityManager
      ->getRepository(FacilityAttachmentRecord::class)
      ->findBy(['kind' => 'floor_plan']);
    self::assertCount(2, $floorPlans);

    foreach ($floorPlans as $floorPlan) {
      self::assertTrue($floorPlan->isPrimaryPlan);
      self::assertSame(2400, $floorPlan->imageWidth);
      self::assertSame(1600, $floorPlan->imageHeight);
      self::assertGreaterThan(0, $floorPlan->size);
    }
    self::assertSame(1, $this->entityManager->getRepository(FacilityRecord::class)->count(['status' => 'archived']));
    self::assertTrue($facilityFixtures->hasReference(FacilityFixtures::AREA_REFERENCE, FacilityRecord::class));
    self::assertTrue($facilityFixtures->hasReference(FacilityFixtures::STORAGE_ROOM_REFERENCE, FacilityRecord::class));

    /** @var FacilityRecord $zone */
    $zone = $facilityFixtures->getReference(FacilityFixtures::ZONE_REFERENCE, FacilityRecord::class);
    /** @var FacilityRecord $area */
    $area = $facilityFixtures->getReference(FacilityFixtures::AREA_REFERENCE, FacilityRecord::class);
    /** @var FacilityRecord $storageRoom */
    $storageRoom = $facilityFixtures->getReference(FacilityFixtures::STORAGE_ROOM_REFERENCE, FacilityRecord::class);

    self::assertNotNull($zone->parentFacility);
    self::assertNotNull($area->parentFacility);
    self::assertSame($zone->id, $area->parentFacility->id);

    // The floor's own outline must point at the floor's own primary plan: the
    // building model only accepts it as an outline in that case, and falls
    // through to a bounding box otherwise. An `area` nested in a `zone`, both
    // with geometry, is what exercises the geometric-leaf rule downstream.
    /** @var FacilityRecord $floorOne */
    $floorOne = $facilityFixtures->getReference(FacilityFixtures::FLOOR_ONE_REFERENCE, FacilityRecord::class);
    self::assertSame(0, $floorOne->levelIndex);
    self::assertNotNull($floorOne->planGeometry);
    self::assertSame(FacilityFixtures::FLOOR_ONE_PLAN_ID, $floorOne->planGeometry['attachmentId']);
    self::assertNotNull($zone->planGeometry);
    self::assertNotNull($area->planGeometry);
    self::assertSame(FacilityFixtures::FLOOR_ONE_PLAN_ID, $area->planGeometry['attachmentId']);

    /** @var FacilityRecord $floorTwo */
    $floorTwo = $facilityFixtures->getReference(FacilityFixtures::FLOOR_TWO_REFERENCE, FacilityRecord::class);
    self::assertSame(1, $floorTwo->levelIndex);
    self::assertNotNull($floorTwo->planGeometry);
    self::assertSame(FacilityFixtures::FLOOR_TWO_PLAN_ID, $floorTwo->planGeometry['attachmentId']);
    self::assertNotNull($storageRoom->parentFacility);
    self::assertSame(OrganizationFixtures::ORGANIZATION_ID, $zone->organization?->id);
  }
}
