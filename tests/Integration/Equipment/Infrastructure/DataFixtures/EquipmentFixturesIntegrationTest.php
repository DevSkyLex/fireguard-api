<?php

declare(strict_types=1);

namespace Tests\Integration\Equipment\Infrastructure\DataFixtures;

use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Infrastructure\DataFixtures\EquipmentFixtures;
use Equipment\Infrastructure\Persistence\Doctrine\Record\{EquipmentAttachmentRecord, EquipmentMaintenanceLogRecord, EquipmentRecord, EquipmentTagRecord, TagRecord};
use Facility\Infrastructure\DataFixtures\FacilityFixtures;
use Organization\Infrastructure\DataFixtures\OrganizationFixtures;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

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

    $executor = new ORMExecutor($this->entityManager);
    $executor->execute($loader->getFixtures(), true);

    self::assertSame(42, $this->entityManager->getRepository(EquipmentRecord::class)->count([]));
    self::assertSame(2, $this->entityManager->getRepository(TagRecord::class)->count([]));
    self::assertSame(3, $this->entityManager->getRepository(EquipmentTagRecord::class)->count([]));
    self::assertSame(1, $this->entityManager->getRepository(EquipmentAttachmentRecord::class)->count([]));
    self::assertSame(1, $this->entityManager->getRepository(EquipmentMaintenanceLogRecord::class)->count([]));

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
