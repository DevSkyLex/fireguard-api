<?php

declare(strict_types=1);

namespace Tests\Integration\Inspection\Infrastructure\DataFixtures;

use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Infrastructure\DataFixtures\EquipmentFixtures;
use Facility\Infrastructure\DataFixtures\FacilityFixtures;
use Inspection\Infrastructure\DataFixtures\InspectionFixtures;
use Inspection\Infrastructure\Persistence\Doctrine\Record\{ChecklistItemRecord, ChecklistRecord, InspectionRecord, NonConformityRecord};
use Organization\Infrastructure\DataFixtures\OrganizationFixtures;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Shared\Infrastructure\DataFixtures\SeedTimeline;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_unique;

#[CoversClass(className: InspectionFixtures::class)]
final class InspectionFixturesIntegrationTest extends KernelTestCase
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
  public function testLoadPersistsInspectionChecklistAndNonConformities(): void
  {
    /** @var OrganizationFixtures $organizationFixtures */
    $organizationFixtures = static::getContainer()->get(OrganizationFixtures::class);
    /** @var FacilityFixtures $facilityFixtures */
    $facilityFixtures = static::getContainer()->get(FacilityFixtures::class);
    /** @var EquipmentFixtures $equipmentFixtures */
    $equipmentFixtures = static::getContainer()->get(EquipmentFixtures::class);
    /** @var InspectionFixtures $inspectionFixtures */
    $inspectionFixtures = static::getContainer()->get(InspectionFixtures::class);

    $loader = new Loader();
    $loader->addFixture($organizationFixtures);
    $loader->addFixture($facilityFixtures);
    $loader->addFixture($equipmentFixtures);
    $loader->addFixture($inspectionFixtures);

    $executor = new ORMExecutor($this->entityManager, new ORMPurger($this->entityManager));
    // Purge before loading: the test databases carry the seeded baseline, so
    // appending on top of it collides on primary keys and makes the counts
    // below meaningless. DAMA rolls the purge back with the rest of the test.
    $executor->execute($loader->getFixtures(), false);

    self::assertSame(2, $this->entityManager->getRepository(ChecklistRecord::class)->count([]));
    self::assertSame(8, $this->entityManager->getRepository(ChecklistItemRecord::class)->count([]));
    self::assertSame(197, $this->entityManager->getRepository(InspectionRecord::class)->count([]));
    self::assertSame(100, $this->entityManager->getRepository(NonConformityRecord::class)->count([]));

    /** @var InspectionRecord $passingInspection */
    $passingInspection = $inspectionFixtures->getReference(InspectionFixtures::PASSING_INSPECTION_REFERENCE, InspectionRecord::class);
    /** @var InspectionRecord $failingInspection */
    $failingInspection = $inspectionFixtures->getReference(InspectionFixtures::FAILING_INSPECTION_REFERENCE, InspectionRecord::class);
    self::assertTrue($inspectionFixtures->hasReference(InspectionFixtures::ANNUAL_CHECKLIST_REFERENCE, ChecklistRecord::class));

    /** @var list<InspectionRecord> $inspections */
    $inspections = $this->entityManager->getRepository(InspectionRecord::class)->findBy([], ['performedAt' => 'ASC']);
    /** @var list<NonConformityRecord> $nonConformities */
    $nonConformities = $this->entityManager->getRepository(NonConformityRecord::class)->findBy([], ['createdAt' => 'ASC']);

    $inspectionDays = [];
    foreach ($inspections as $inspection) {
      $inspectionDays[] = $inspection->performedAt->format('Y-m-d');
    }

    $openedDays = [];
    $resolvedDays = [];
    foreach ($nonConformities as $nonConformity) {
      $openedDays[] = $nonConformity->createdAt->format('Y-m-d');
      if (null !== $nonConformity->resolvedAt) {
        $resolvedDays[] = $nonConformity->resolvedAt->format('Y-m-d');
      }
    }

    self::assertSame('pass', $passingInspection->result);
    self::assertSame(SeedTimeline::at('2026-03-05T09:00:00+00:00')->format('Y-m-d'), $passingInspection->performedAt->format('Y-m-d'));
    self::assertSame('submitted', $failingInspection->status);
    self::assertSame(SeedTimeline::at('2026-03-20T14:00:00+00:00')->format('Y-m-d'), $failingInspection->performedAt->format('Y-m-d'));
    self::assertSame(OrganizationFixtures::ORGANIZATION_ID, $failingInspection->organization?->id);
    self::assertCount(39, array_unique($inspectionDays));
    self::assertCount(26, array_unique($openedDays));
    self::assertCount(22, array_unique($resolvedDays));
  }
}
