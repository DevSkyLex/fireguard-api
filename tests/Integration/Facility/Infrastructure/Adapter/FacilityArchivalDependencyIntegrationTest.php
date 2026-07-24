<?php

declare(strict_types=1);

namespace Tests\Integration\Facility\Infrastructure\Adapter;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Application\Port\Outbound\FacilityNamingPort;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Facility\Application\Port\Outbound\{FacilityEquipmentDependencyPort, FacilityInspectionDependencyPort};
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Inspection\Infrastructure\Persistence\Doctrine\Record\InspectionRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Verifies the Equipment / Inspection dependency adapters (used by the facility
 * archival guard) against a real database, including the active-status filtering.
 */
final class FacilityArchivalDependencyIntegrationTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655444000';

  private const string FACILITY_WITH_ACTIVE = '550e8400-e29b-41d4-a716-446655444010';

  private const string FACILITY_WITHOUT_ACTIVE = '550e8400-e29b-41d4-a716-446655444011';

  private EntityManagerInterface $entityManager;

  private FacilityEquipmentDependencyPort $equipmentDependency;

  private FacilityInspectionDependencyPort $inspectionDependency;

  private FacilityNamingPort $facilityNaming;

  protected function setUp(): void
  {
    self::bootKernel();
    $container = static::getContainer();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = $container->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;
    /** @var FacilityEquipmentDependencyPort $equipmentDependency */
    $equipmentDependency = $container->get(FacilityEquipmentDependencyPort::class);
    $this->equipmentDependency = $equipmentDependency;
    /** @var FacilityInspectionDependencyPort $inspectionDependency */
    $inspectionDependency = $container->get(FacilityInspectionDependencyPort::class);
    $this->inspectionDependency = $inspectionDependency;
    /** @var FacilityNamingPort $facilityNaming */
    $facilityNaming = $container->get(FacilityNamingPort::class);
    $this->facilityNaming = $facilityNaming;

    $this->removeOrganization(self::ORGANIZATION_ID);
  }

  protected function tearDown(): void
  {
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testReportsActiveEquipmentDependents(): void
  {
    $organization = $this->createOrganization();
    $facilityWithActive = $this->createFacility(self::FACILITY_WITH_ACTIVE, $organization);
    $facilityWithoutActive = $this->createFacility(self::FACILITY_WITHOUT_ACTIVE, $organization);

    $this->createEquipment('550e8400-e29b-41d4-a716-446655444020', $organization, self::FACILITY_WITH_ACTIVE, 'operational');
    $this->createEquipment('550e8400-e29b-41d4-a716-446655444021', $organization, self::FACILITY_WITHOUT_ACTIVE, 'decommissioned');

    $this->entityManager->flush();
    $this->entityManager->clear();

    self::assertTrue($this->equipmentDependency->hasActiveEquipmentInFacility(self::ORGANIZATION_ID, self::FACILITY_WITH_ACTIVE));
    // Only decommissioned equipment remains, so it no longer blocks archiving.
    self::assertFalse($this->equipmentDependency->hasActiveEquipmentInFacility(self::ORGANIZATION_ID, self::FACILITY_WITHOUT_ACTIVE));

    unset($facilityWithActive, $facilityWithoutActive);
  }

  #[Test]
  public function testReportsInProgressInspectionDependents(): void
  {
    $organization = $this->createOrganization();
    $this->createFacility(self::FACILITY_WITH_ACTIVE, $organization);
    $this->createFacility(self::FACILITY_WITHOUT_ACTIVE, $organization);

    $this->createInspection('550e8400-e29b-41d4-a716-446655444030', $organization, self::FACILITY_WITH_ACTIVE, 'draft');
    $this->createInspection('550e8400-e29b-41d4-a716-446655444031', $organization, self::FACILITY_WITHOUT_ACTIVE, 'closed');

    $this->entityManager->flush();
    $this->entityManager->clear();

    self::assertTrue($this->inspectionDependency->hasActiveInspectionInFacility(self::ORGANIZATION_ID, self::FACILITY_WITH_ACTIVE));
    // A closed inspection is historical, so it no longer blocks archiving.
    self::assertFalse($this->inspectionDependency->hasActiveInspectionInFacility(self::ORGANIZATION_ID, self::FACILITY_WITHOUT_ACTIVE));
  }

  #[Test]
  public function testCountsActiveEquipmentPerFacilityInOneCall(): void
  {
    $organization = $this->createOrganization();
    $this->createFacility(self::FACILITY_WITH_ACTIVE, $organization);
    $this->createFacility(self::FACILITY_WITHOUT_ACTIVE, $organization);

    $this->createEquipment('550e8400-e29b-41d4-a716-446655444040', $organization, self::FACILITY_WITH_ACTIVE, 'operational');
    $this->createEquipment('550e8400-e29b-41d4-a716-446655444041', $organization, self::FACILITY_WITH_ACTIVE, 'operational');
    // Decommissioned equipment is still equipment, but not active equipment.
    $this->createEquipment('550e8400-e29b-41d4-a716-446655444042', $organization, self::FACILITY_WITH_ACTIVE, 'decommissioned');
    $this->createEquipment('550e8400-e29b-41d4-a716-446655444043', $organization, self::FACILITY_WITHOUT_ACTIVE, 'decommissioned');

    $this->entityManager->flush();
    $this->entityManager->clear();

    $counts = $this->equipmentDependency->countActiveEquipmentByFacility(
      self::ORGANIZATION_ID,
      [self::FACILITY_WITH_ACTIVE, self::FACILITY_WITHOUT_ACTIVE],
    );

    self::assertSame(2, $counts[self::FACILITY_WITH_ACTIVE] ?? 0);
    // Absent rather than zero — the caller defaults it.
    self::assertArrayNotHasKey(self::FACILITY_WITHOUT_ACTIVE, $counts);
  }

  // Resolved through the container, not constructed by hand: this adapter needs
  // the `main` entity manager, and autowiring the default one produced a
  // perfectly typed object whose every query failed at runtime.
  #[Test]
  public function testResolvesFacilityNamesThroughTheContainerWiredAdapter(): void
  {
    $organization = $this->createOrganization();
    $this->createFacility(self::FACILITY_WITH_ACTIVE, $organization);

    $this->entityManager->flush();
    $this->entityManager->clear();

    $names = $this->facilityNaming->findNamesByIds([
      self::FACILITY_WITH_ACTIVE,
      '550e8400-e29b-41d4-a716-446655444099',
    ]);

    self::assertSame('Facility ' . self::FACILITY_WITH_ACTIVE, $names[self::FACILITY_WITH_ACTIVE] ?? null);
    // An identifier we cannot resolve is absent, never a blank name.
    self::assertArrayNotHasKey('550e8400-e29b-41d4-a716-446655444099', $names);
  }

  private function createOrganization(): OrganizationRecord
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $organization->name = 'Facility Dependency Test';
    $organization->slug = 'facility-dependency-test';
    $organization->ownerUserId = '550e8400-e29b-41d4-a716-446655444900';
    $organization->createdByUserId = '550e8400-e29b-41d4-a716-446655444900';
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-02-12T10:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;
    $this->entityManager->persist($organization);

    return $organization;
  }

  private function createFacility(string $id, OrganizationRecord $organization): FacilityRecord
  {
    $facility = new FacilityRecord();
    $facility->id = $id;
    $facility->organization = $organization;
    $facility->parentFacility = null;
    $facility->type = 'site';
    $facility->name = 'Facility ' . $id;
    $facility->code = null;
    $facility->status = 'active';
    $facility->address = null;
    $facility->metadata = [];
    $facility->createdAt = new DateTimeImmutable('2026-02-12T10:00:00+00:00');
    $facility->updatedAt = $facility->createdAt;
    $this->entityManager->persist($facility);

    return $facility;
  }

  private function createEquipment(string $id, OrganizationRecord $organization, string $facilityId, string $status): void
  {
    $equipment = new EquipmentRecord();
    $equipment->id = $id;
    $equipment->organization = $organization;
    $equipment->facilityId = $facilityId;
    $equipment->recordStatus = 'published';
    $equipment->type = 'fire_extinguisher';
    $equipment->status = $status;
    $equipment->createdAt = new DateTimeImmutable('2026-02-12T10:00:00+00:00');
    $equipment->updatedAt = $equipment->createdAt;
    $this->entityManager->persist($equipment);
  }

  private function createInspection(string $id, OrganizationRecord $organization, string $facilityId, string $status): void
  {
    $inspection = new InspectionRecord();
    $inspection->id = $id;
    $inspection->organization = $organization;
    $inspection->facilityId = $facilityId;
    $inspection->equipmentId = '550e8400-e29b-41d4-a716-446655444040';
    $inspection->recordStatus = 'published';
    $inspection->status = $status;
    $inspection->result = 'pass';
    $inspection->inspectorType = 'external';
    $inspection->inspectorName = 'Inspector';
    $inspection->performedAt = new DateTimeImmutable('2026-02-12T10:00:00+00:00');
    $inspection->createdAt = new DateTimeImmutable('2026-02-12T10:00:00+00:00');
    $inspection->updatedAt = $inspection->createdAt;
    $this->entityManager->persist($inspection);
  }

  private function removeOrganization(string $id): void
  {
    foreach ([EquipmentRecord::class, InspectionRecord::class, FacilityRecord::class] as $recordClass) {
      foreach ($this->entityManager->getRepository($recordClass)->findBy(['organization' => $id]) as $record) {
        $this->entityManager->remove($record);
      }
    }
    $organization = $this->entityManager->find(OrganizationRecord::class, $id);
    if ($organization instanceof OrganizationRecord) {
      $this->entityManager->remove($organization);
    }
    $this->entityManager->flush();
  }
}
