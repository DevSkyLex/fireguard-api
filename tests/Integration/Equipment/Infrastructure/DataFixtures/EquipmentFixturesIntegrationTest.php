<?php

declare(strict_types=1);

namespace Tests\Integration\Equipment\Infrastructure\DataFixtures;

use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Infrastructure\DataFixtures\EquipmentFixtures;
use Equipment\Infrastructure\Persistence\Doctrine\Record\{EquipmentAttachmentRecord, EquipmentMaintenanceLogRecord, EquipmentRecord, EquipmentTagRecord, TagRecord};
use Facility\Infrastructure\DataFixtures\FacilityFixtures;
use Organization\Infrastructure\DataFixtures\OrganizationFixtures;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function count;

#[CoversClass(className: EquipmentFixtures::class)]
final class EquipmentFixturesIntegrationTest extends KernelTestCase
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
  public function testLoadPersistsEquipmentTagsAndOperationalArtifacts(): void
  {
    /** @var OrganizationFixtures $organizationFixtures */
    $organizationFixtures = static::getContainer()->get(OrganizationFixtures::class);
    /** @var FacilityFixtures $facilityFixtures */
    $facilityFixtures = static::getContainer()->get(FacilityFixtures::class);
    /** @var EquipmentFixtures $equipmentFixtures */
    $equipmentFixtures = static::getContainer()->get(EquipmentFixtures::class);

    $loader = new Loader();
    $loader->addFixture($organizationFixtures);
    $loader->addFixture($facilityFixtures);
    $loader->addFixture($equipmentFixtures);

    $executor = new ORMExecutor($this->entityManager, new ORMPurger($this->entityManager));
    // Purge before loading: the test databases carry the seeded baseline, so
    // appending on top of it collides on primary keys and makes the counts
    // below meaningless. DAMA rolls the purge back with the rest of the test.
    $executor->execute($loader->getFixtures(), false);

    // 10 hand-written assets plus the bulk/regional inventory and the extra
    // regional pool that pads the facility pagination.
    self::assertSame(
      10 + count(EquipmentFixtures::ADDITIONAL_EQUIPMENT_SEEDS) + EquipmentFixtures::EXTRA_REGIONAL_EQUIPMENT_COUNT,
      $this->entityManager->getRepository(EquipmentRecord::class)->count([]),
    );
    self::assertSame(2 + count(EquipmentFixtures::ADDITIONAL_TAG_SEEDS), $this->entityManager->getRepository(TagRecord::class)->count([]));
    self::assertSame(25, $this->entityManager->getRepository(EquipmentTagRecord::class)->count([]));
    self::assertSame(10, $this->entityManager->getRepository(EquipmentAttachmentRecord::class)->count([]));
    self::assertSame(9, $this->entityManager->getRepository(EquipmentMaintenanceLogRecord::class)->count([]));

    /** @var EquipmentRecord $extinguisher */
    $extinguisher = $equipmentFixtures->getReference(EquipmentFixtures::EXTINGUISHER_REFERENCE, EquipmentRecord::class);
    /** @var EquipmentRecord $hydrant */
    $hydrant = $equipmentFixtures->getReference(EquipmentFixtures::HYDRANT_REFERENCE, EquipmentRecord::class);
    /** @var EquipmentRecord $heatDetector */
    $heatDetector = $equipmentFixtures->getReference(EquipmentFixtures::HEAT_DETECTOR_REFERENCE, EquipmentRecord::class);

    self::assertNotNull($extinguisher->facilityId);
    self::assertNull($hydrant->facilityId);
    self::assertNotNull($heatDetector->facilityId);
    self::assertSame(OrganizationFixtures::ORGANIZATION_ID, $extinguisher->organization?->id);
  }
}
