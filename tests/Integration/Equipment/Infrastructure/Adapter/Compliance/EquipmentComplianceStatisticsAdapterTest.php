<?php

declare(strict_types=1);

namespace Tests\Integration\Equipment\Infrastructure\Adapter\Compliance;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Infrastructure\Adapter\Compliance\EquipmentComplianceStatisticsAdapter;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test EquipmentComplianceStatisticsAdapter.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(EquipmentComplianceStatisticsAdapter::class)]
final class EquipmentComplianceStatisticsAdapterTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = '770e8400-e29b-41d4-a716-4466551a0001';

  private const string OTHER_ORGANIZATION_ID = '770e8400-e29b-41d4-a716-4466551a0002';

  private const string FACILITY_ID = '770e8400-e29b-41d4-a716-4466551a00f1';

  private EntityManagerInterface $entityManager;

  private EquipmentComplianceStatisticsAdapter $adapter;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    $this->adapter = new EquipmentComplianceStatisticsAdapter($this->entityManager);

    $this->createOrganization(self::ORGANIZATION_ID, 'Compliance Stats Test', 'compliance-stats-test');
    $this->createOrganization(self::OTHER_ORGANIZATION_ID, 'Compliance Stats Other', 'compliance-stats-other');
    $this->entityManager->flush();
  }

  protected function tearDown(): void
  {
    $this->cleanup();
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testEquipmentInventoryByFacilityCountsPublishedActiveAndTotal(): void
  {
    // Facility F1: one operational (active) + one decommissioned (inactive) = total 2, active 1.
    $this->createEquipment('770e8400-e29b-41d4-a716-4466551a0010', self::ORGANIZATION_ID, self::FACILITY_ID, 'operational', 'published');
    $this->createEquipment('770e8400-e29b-41d4-a716-4466551a0011', self::ORGANIZATION_ID, self::FACILITY_ID, 'decommissioned', 'published');
    // Unassigned (facility null): one operational = total 1, active 1.
    $this->createEquipment('770e8400-e29b-41d4-a716-4466551a0012', self::ORGANIZATION_ID, null, 'operational', 'published');
    // Draft record on F1 must be excluded entirely.
    $this->createEquipment('770e8400-e29b-41d4-a716-4466551a0013', self::ORGANIZATION_ID, self::FACILITY_ID, 'operational', 'draft');
    // Other organization must not leak into the result.
    $this->createEquipment('770e8400-e29b-41d4-a716-4466551a0014', self::OTHER_ORGANIZATION_ID, self::FACILITY_ID, 'operational', 'published');
    $this->entityManager->flush();
    $this->entityManager->clear();

    $inventory = $this->adapter->equipmentInventoryByFacility(self::ORGANIZATION_ID);

    self::assertArrayHasKey(self::FACILITY_ID, $inventory);
    self::assertSame(['total' => 2, 'active' => 1], $inventory[self::FACILITY_ID]);
    self::assertArrayHasKey('unassigned', $inventory);
    self::assertSame(['total' => 1, 'active' => 1], $inventory['unassigned']);
  }

  #[Test]
  public function testEquipmentInventoryByFacilityIsEmptyWhenOrganizationHasNoEquipment(): void
  {
    self::assertSame([], $this->adapter->equipmentInventoryByFacility(self::ORGANIZATION_ID));
  }

  private function createOrganization(string $id, string $name, string $slug): void
  {
    $organization = new OrganizationRecord();
    $organization->id = $id;
    $organization->name = $name;
    $organization->slug = $slug;
    $organization->ownerUserId = '770e8400-e29b-41d4-a716-4466551a9000';
    $organization->createdByUserId = '770e8400-e29b-41d4-a716-4466551a9000';
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;
    $this->entityManager->persist($organization);
  }

  private function createEquipment(
    string $id,
    string $organizationId,
    ?string $facilityId,
    string $status,
    string $recordStatus,
  ): void {
    $organization = $this->entityManager->getReference(OrganizationRecord::class, $organizationId);

    $equipment = new EquipmentRecord();
    $equipment->id = $id;
    $equipment->organization = $organization;
    $equipment->facilityId = $facilityId;
    $equipment->type = 'fire_extinguisher';
    $equipment->status = $status;
    $equipment->recordStatus = $recordStatus;
    $equipment->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $equipment->updatedAt = $equipment->createdAt;
    $this->entityManager->persist($equipment);
  }

  private function cleanup(): void
  {
    $connection = $this->entityManager->getConnection();
    $connection->executeStatement(
      'DELETE FROM equipment WHERE organization_id IN (:organizationIds)',
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
