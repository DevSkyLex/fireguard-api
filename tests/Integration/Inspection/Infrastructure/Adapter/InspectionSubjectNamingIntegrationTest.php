<?php

declare(strict_types=1);

namespace Tests\Integration\Inspection\Infrastructure\Adapter;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Inspection\Application\Port\Outbound\{EquipmentNamingPort, FacilityNamingPort};
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Verifies the Inspection module's two naming adapters against a real database.
 *
 * Both ports are resolved **through the container** rather than constructed by
 * hand. These adapters need the `main` entity manager, and autowiring the
 * default one produces a perfectly typed object whose every query fails at
 * runtime — a mistake no mock-based test can see, and one this codebase has
 * already made once.
 */
final class InspectionSubjectNamingIntegrationTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655445000';

  private const string FACILITY_ID = '550e8400-e29b-41d4-a716-446655445010';

  private const string EQUIPMENT_WITH_SERIAL = '550e8400-e29b-41d4-a716-446655445020';

  private const string EQUIPMENT_WITHOUT_SERIAL = '550e8400-e29b-41d4-a716-446655445021';

  private const string UNKNOWN_ID = '550e8400-e29b-41d4-a716-446655445099';

  private EntityManagerInterface $entityManager;

  private EquipmentNamingPort $equipmentNaming;

  private FacilityNamingPort $facilityNaming;

  protected function setUp(): void
  {
    self::bootKernel();
    $container = static::getContainer();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = $container->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;
    /** @var EquipmentNamingPort $equipmentNaming */
    $equipmentNaming = $container->get(EquipmentNamingPort::class);
    $this->equipmentNaming = $equipmentNaming;
    /** @var FacilityNamingPort $facilityNaming */
    $facilityNaming = $container->get(FacilityNamingPort::class);
    $this->facilityNaming = $facilityNaming;

    $this->removeOrganization();
    $this->seed();
  }

  protected function tearDown(): void
  {
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testResolvesEquipmentSerialNumbers(): void
  {
    $serials = $this->equipmentNaming->findSerialNumbersByIds([
      self::EQUIPMENT_WITH_SERIAL,
      self::EQUIPMENT_WITHOUT_SERIAL,
      self::UNKNOWN_ID,
    ]);

    self::assertSame('SN-4711', $serials[self::EQUIPMENT_WITH_SERIAL] ?? null);
    // Equipment with no recorded serial is absent, never an empty string: the
    // detail view must fall back to something else rather than print nothing.
    self::assertArrayNotHasKey(self::EQUIPMENT_WITHOUT_SERIAL, $serials);
    self::assertArrayNotHasKey(self::UNKNOWN_ID, $serials);
  }

  #[Test]
  public function testResolvesFacilityNames(): void
  {
    $names = $this->facilityNaming->findNamesByIds([self::FACILITY_ID, self::UNKNOWN_ID]);

    self::assertSame('Northgate Plant', $names[self::FACILITY_ID] ?? null);
    self::assertArrayNotHasKey(self::UNKNOWN_ID, $names);
  }

  #[Test]
  public function testReturnsNothingForAnEmptyRequest(): void
  {
    self::assertSame([], $this->equipmentNaming->findSerialNumbersByIds([]));
    self::assertSame([], $this->facilityNaming->findNamesByIds([]));
  }

  private function seed(): void
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $organization->name = 'Inspection Naming Test';
    $organization->slug = 'inspection-naming-test';
    $organization->ownerUserId = '550e8400-e29b-41d4-a716-446655445900';
    $organization->createdByUserId = '550e8400-e29b-41d4-a716-446655445900';
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-02-12T10:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;
    $this->entityManager->persist($organization);

    $facility = new FacilityRecord();
    $facility->id = self::FACILITY_ID;
    $facility->organization = $organization;
    $facility->parentFacility = null;
    $facility->type = 'site';
    $facility->name = 'Northgate Plant';
    $facility->code = null;
    $facility->status = 'active';
    $facility->address = null;
    $facility->metadata = [];
    $facility->createdAt = $organization->createdAt;
    $facility->updatedAt = $organization->createdAt;
    $this->entityManager->persist($facility);

    foreach ([self::EQUIPMENT_WITH_SERIAL => 'SN-4711', self::EQUIPMENT_WITHOUT_SERIAL => null] as $id => $serial) {
      $equipment = new EquipmentRecord();
      $equipment->id = $id;
      $equipment->organization = $organization;
      $equipment->facilityId = self::FACILITY_ID;
      $equipment->recordStatus = 'published';
      $equipment->type = 'fire_extinguisher';
      $equipment->status = 'operational';
      $equipment->serialNumber = $serial;
      $equipment->createdAt = $organization->createdAt;
      $equipment->updatedAt = $organization->createdAt;
      $this->entityManager->persist($equipment);
    }

    $this->entityManager->flush();
    $this->entityManager->clear();
  }

  private function removeOrganization(): void
  {
    foreach ([EquipmentRecord::class, FacilityRecord::class] as $recordClass) {
      foreach ($this->entityManager->getRepository($recordClass)->findBy(['organization' => self::ORGANIZATION_ID]) as $record) {
        $this->entityManager->remove($record);
      }
    }

    $organization = $this->entityManager->find(OrganizationRecord::class, self::ORGANIZATION_ID);
    if ($organization instanceof OrganizationRecord) {
      $this->entityManager->remove($organization);
    }

    $this->entityManager->flush();
  }
}
