<?php

declare(strict_types=1);

namespace Tests\Integration\Equipment\Infrastructure\Adapter\Facility;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Infrastructure\Adapter\Facility\FacilityEquipmentDependencyAdapter;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test FacilityEquipmentDependencyAdapter.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(FacilityEquipmentDependencyAdapter::class)]
final class FacilityEquipmentDependencyAdapterTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = '771e8400-e29b-41d4-a716-4466551b0001';

  private const string OTHER_ORGANIZATION_ID = '771e8400-e29b-41d4-a716-4466551b0002';

  private const string FACILITY_WITH_ACTIVE = '771e8400-e29b-41d4-a716-4466551b00f1';

  private const string FACILITY_WITH_DECOMMISSIONED = '771e8400-e29b-41d4-a716-4466551b00f2';

  private const string FACILITY_EMPTY = '771e8400-e29b-41d4-a716-4466551b00f3';

  private EntityManagerInterface $entityManager;

  private FacilityEquipmentDependencyAdapter $adapter;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    $this->adapter = new FacilityEquipmentDependencyAdapter($this->entityManager);

    $this->createOrganization(self::ORGANIZATION_ID, 'Facility Dep Test', 'facility-dep-test');
    $this->createOrganization(self::OTHER_ORGANIZATION_ID, 'Facility Dep Other', 'facility-dep-other');
    $this->entityManager->flush();
  }

  protected function tearDown(): void
  {
    $this->cleanup();
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testHasActiveEquipmentInFacilityIsTrueOnlyForPublishedNonDecommissioned(): void
  {
    $this->createEquipment('771e8400-e29b-41d4-a716-4466551b0010', self::ORGANIZATION_ID, self::FACILITY_WITH_ACTIVE, 'operational', 'published');
    // Decommissioned equipment does not keep a facility "active".
    $this->createEquipment('771e8400-e29b-41d4-a716-4466551b0011', self::ORGANIZATION_ID, self::FACILITY_WITH_DECOMMISSIONED, 'decommissioned', 'published');
    // A draft on the empty facility must not count either.
    $this->createEquipment('771e8400-e29b-41d4-a716-4466551b0012', self::ORGANIZATION_ID, self::FACILITY_EMPTY, 'operational', 'draft');
    $this->entityManager->flush();
    $this->entityManager->clear();

    self::assertTrue($this->adapter->hasActiveEquipmentInFacility(self::ORGANIZATION_ID, self::FACILITY_WITH_ACTIVE));
    self::assertFalse($this->adapter->hasActiveEquipmentInFacility(self::ORGANIZATION_ID, self::FACILITY_WITH_DECOMMISSIONED));
    self::assertFalse($this->adapter->hasActiveEquipmentInFacility(self::ORGANIZATION_ID, self::FACILITY_EMPTY));
  }

  #[Test]
  public function testHasActiveEquipmentInFacilityIsScopedByOrganization(): void
  {
    $this->createEquipment('771e8400-e29b-41d4-a716-4466551b0020', self::OTHER_ORGANIZATION_ID, self::FACILITY_WITH_ACTIVE, 'operational', 'published');
    $this->entityManager->flush();
    $this->entityManager->clear();

    // Requesting organization owns nothing in that facility.
    self::assertFalse($this->adapter->hasActiveEquipmentInFacility(self::ORGANIZATION_ID, self::FACILITY_WITH_ACTIVE));
    self::assertTrue($this->adapter->hasActiveEquipmentInFacility(self::OTHER_ORGANIZATION_ID, self::FACILITY_WITH_ACTIVE));
  }

  #[Test]
  public function testCountActiveEquipmentByFacilityGroupsAndFiltersDecommissioned(): void
  {
    $this->createEquipment('771e8400-e29b-41d4-a716-4466551b0030', self::ORGANIZATION_ID, self::FACILITY_WITH_ACTIVE, 'operational', 'published');
    $this->createEquipment('771e8400-e29b-41d4-a716-4466551b0031', self::ORGANIZATION_ID, self::FACILITY_WITH_ACTIVE, 'under_maintenance', 'published');
    $this->createEquipment('771e8400-e29b-41d4-a716-4466551b0032', self::ORGANIZATION_ID, self::FACILITY_WITH_DECOMMISSIONED, 'decommissioned', 'published');
    $this->entityManager->flush();
    $this->entityManager->clear();

    $counts = $this->adapter->countActiveEquipmentByFacility(
      self::ORGANIZATION_ID,
      [self::FACILITY_WITH_ACTIVE, self::FACILITY_WITH_DECOMMISSIONED, self::FACILITY_EMPTY],
    );

    self::assertSame(2, $counts[self::FACILITY_WITH_ACTIVE] ?? 0);
    // Only decommissioned equipment there → excluded from the grouped result.
    self::assertArrayNotHasKey(self::FACILITY_WITH_DECOMMISSIONED, $counts);
    self::assertArrayNotHasKey(self::FACILITY_EMPTY, $counts);
  }

  #[Test]
  public function testCountActiveEquipmentByFacilityReturnsEmptyForEmptyInput(): void
  {
    self::assertSame([], $this->adapter->countActiveEquipmentByFacility(self::ORGANIZATION_ID, []));
  }

  private function createOrganization(string $id, string $name, string $slug): void
  {
    $organization = new OrganizationRecord();
    $organization->id = $id;
    $organization->name = $name;
    $organization->slug = $slug;
    $organization->ownerUserId = '771e8400-e29b-41d4-a716-4466551b9000';
    $organization->createdByUserId = '771e8400-e29b-41d4-a716-4466551b9000';
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
