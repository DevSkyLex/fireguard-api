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
    self::assertSame(9, $this->entityManager->getRepository(FacilityAttachmentRecord::class)->count([]));
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
    self::assertNotNull($storageRoom->parentFacility);
    self::assertSame(OrganizationFixtures::ORGANIZATION_ID, $zone->organization?->id);
  }
}
