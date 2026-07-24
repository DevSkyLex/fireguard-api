<?php

declare(strict_types=1);

namespace Tests\Integration\Facility\Infrastructure\Adapter\Equipment;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Infrastructure\Adapter\Equipment\FacilityValidationAdapter;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Facility\Infrastructure\Persistence\Doctrine\Repository\FacilityRepository;
use InvalidArgumentException;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test FacilityValidationAdapter.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(FacilityValidationAdapter::class)]
final class FacilityValidationAdapterTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-4466554a5000';

  private const string OTHER_ORGANIZATION_ID = '550e8400-e29b-41d4-a716-4466554a5001';

  private const string ACTIVE_FACILITY_ID = '550e8400-e29b-41d4-a716-4466554a5010';

  private const string ARCHIVED_FACILITY_ID = '550e8400-e29b-41d4-a716-4466554a5011';

  private const string UNKNOWN_ID = '550e8400-e29b-41d4-a716-4466554a5099';

  private EntityManagerInterface $entityManager;

  private FacilityValidationAdapter $adapter;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;
    $this->adapter = new FacilityValidationAdapter(new FacilityRepository($this->entityManager));

    $this->removeOrganization(self::ORGANIZATION_ID);
    $this->removeOrganization(self::OTHER_ORGANIZATION_ID);
  }

  protected function tearDown(): void
  {
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testAcceptsActiveFacilityInOwningOrganization(): void
  {
    $organization = $this->createOrganization(self::ORGANIZATION_ID, 'facility-equipment-validate-owner');
    $this->createFacility(self::ACTIVE_FACILITY_ID, $organization, 'active');

    $this->entityManager->flush();
    $this->entityManager->clear();

    $this->adapter->assertFacilityIsAssignable(self::ACTIVE_FACILITY_ID, self::ORGANIZATION_ID);

    // No exception means the facility is assignable.
    $this->expectNotToPerformAssertions();
  }

  #[Test]
  public function testRejectsUnknownFacility(): void
  {
    $this->createOrganization(self::ORGANIZATION_ID, 'facility-equipment-validate-unknown');

    $this->entityManager->flush();
    $this->entityManager->clear();

    $this->expectException(InvalidArgumentException::class);

    $this->adapter->assertFacilityIsAssignable(self::UNKNOWN_ID, self::ORGANIZATION_ID);
  }

  #[Test]
  public function testRejectsFacilityFromAnotherOrganization(): void
  {
    $organization = $this->createOrganization(self::ORGANIZATION_ID, 'facility-equipment-validate-tenant');
    $this->createOrganization(self::OTHER_ORGANIZATION_ID, 'facility-equipment-validate-other');
    $this->createFacility(self::ACTIVE_FACILITY_ID, $organization, 'active');

    $this->entityManager->flush();
    $this->entityManager->clear();

    $this->expectException(InvalidArgumentException::class);

    // Tenant isolation: a facility from another organization is not assignable.
    $this->adapter->assertFacilityIsAssignable(self::ACTIVE_FACILITY_ID, self::OTHER_ORGANIZATION_ID);
  }

  #[Test]
  public function testRejectsArchivedFacility(): void
  {
    $organization = $this->createOrganization(self::ORGANIZATION_ID, 'facility-equipment-validate-archived');
    $this->createFacility(self::ARCHIVED_FACILITY_ID, $organization, 'archived');

    $this->entityManager->flush();
    $this->entityManager->clear();

    $this->expectException(InvalidArgumentException::class);

    $this->adapter->assertFacilityIsAssignable(self::ARCHIVED_FACILITY_ID, self::ORGANIZATION_ID);
  }

  private function createOrganization(string $id, string $slug): OrganizationRecord
  {
    $organization = new OrganizationRecord();
    $organization->id = $id;
    $organization->name = 'Facility Equipment Validation Test';
    $organization->slug = $slug;
    $organization->ownerUserId = '550e8400-e29b-41d4-a716-4466554a5900';
    $organization->createdByUserId = '550e8400-e29b-41d4-a716-4466554a5900';
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-02-12T10:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;
    $this->entityManager->persist($organization);

    return $organization;
  }

  private function createFacility(string $id, OrganizationRecord $organization, string $status): void
  {
    $facility = new FacilityRecord();
    $facility->id = $id;
    $facility->organization = $organization;
    $facility->parentFacility = null;
    $facility->type = 'site';
    $facility->name = 'Facility ' . $id;
    $facility->code = null;
    $facility->status = $status;
    $facility->address = null;
    $facility->metadata = [];
    $facility->createdAt = new DateTimeImmutable('2026-02-12T10:00:00+00:00');
    $facility->updatedAt = $facility->createdAt;
    $this->entityManager->persist($facility);
  }

  private function removeOrganization(string $id): void
  {
    foreach ($this->entityManager->getRepository(FacilityRecord::class)->findBy(['organization' => $id]) as $record) {
      $this->entityManager->remove($record);
    }
    $organization = $this->entityManager->find(OrganizationRecord::class, $id);
    if ($organization instanceof OrganizationRecord) {
      $this->entityManager->remove($organization);
    }
    $this->entityManager->flush();
  }
}
