<?php

declare(strict_types=1);

namespace Tests\Integration\Facility\Infrastructure\Adapter\Compliance;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Infrastructure\Adapter\Compliance\FacilityComplianceDirectoryAdapter;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Facility\Infrastructure\Persistence\Doctrine\Repository\FacilityRepository;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_column;

/**
 * Test FacilityComplianceDirectoryAdapter.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(FacilityComplianceDirectoryAdapter::class)]
final class FacilityComplianceDirectoryAdapterTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-4466554a3000';

  private const string OTHER_ORGANIZATION_ID = '550e8400-e29b-41d4-a716-4466554a3001';

  private const string ROOT_ID = '550e8400-e29b-41d4-a716-4466554a3010';

  private const string CHILD_ID = '550e8400-e29b-41d4-a716-4466554a3011';

  private const string ARCHIVED_ID = '550e8400-e29b-41d4-a716-4466554a3012';

  private const string OTHER_FACILITY_ID = '550e8400-e29b-41d4-a716-4466554a3013';

  private EntityManagerInterface $entityManager;

  private FacilityComplianceDirectoryAdapter $adapter;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;
    $this->adapter = new FacilityComplianceDirectoryAdapter(new FacilityRepository($this->entityManager));

    $this->removeOrganization(self::ORGANIZATION_ID);
    $this->removeOrganization(self::OTHER_ORGANIZATION_ID);
  }

  protected function tearDown(): void
  {
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testListsAllFacilitiesIncludingArchivedScopedToOrganization(): void
  {
    $organization = $this->createOrganization(self::ORGANIZATION_ID, 'facility-compliance-owner');
    $otherOrganization = $this->createOrganization(self::OTHER_ORGANIZATION_ID, 'facility-compliance-other');

    $root = $this->createFacility(self::ROOT_ID, $organization, null, 'Head Office', 'site', 'active');
    $this->createFacility(self::CHILD_ID, $organization, $root, 'Building A', 'building', 'active');
    // Archived facilities remain visible in the regulatory register.
    $this->createFacility(self::ARCHIVED_ID, $organization, null, 'Old Site', 'site', 'archived');
    $this->createFacility(self::OTHER_FACILITY_ID, $otherOrganization, null, 'Foreign Site', 'site', 'active');

    $this->entityManager->flush();
    $this->entityManager->clear();

    $rows = $this->adapter->listFacilities(self::ORGANIZATION_ID);

    $ids = array_column($rows, 'id');
    self::assertContains(self::ROOT_ID, $ids);
    self::assertContains(self::CHILD_ID, $ids);
    self::assertContains(self::ARCHIVED_ID, $ids);
    // Tenant isolation: another organization's facilities never leak in.
    self::assertNotContains(self::OTHER_FACILITY_ID, $ids);
    self::assertCount(3, $rows);

    $byId = [];
    foreach ($rows as $row) {
      $byId[$row['id']] = $row;
    }

    self::assertSame('Head Office', $byId[self::ROOT_ID]['name']);
    self::assertSame('site', $byId[self::ROOT_ID]['type']);
    self::assertSame('active', $byId[self::ROOT_ID]['status']);
    self::assertNull($byId[self::ROOT_ID]['parentFacilityId']);
    self::assertSame(self::ROOT_ID, $byId[self::CHILD_ID]['parentFacilityId']);
    self::assertSame('archived', $byId[self::ARCHIVED_ID]['status']);
  }

  #[Test]
  public function testReturnsEmptyDirectoryForOrganizationWithoutFacilities(): void
  {
    $this->createOrganization(self::ORGANIZATION_ID, 'facility-compliance-empty');

    $this->entityManager->flush();
    $this->entityManager->clear();

    self::assertSame([], $this->adapter->listFacilities(self::ORGANIZATION_ID));
  }

  private function createOrganization(string $id, string $slug): OrganizationRecord
  {
    $organization = new OrganizationRecord();
    $organization->id = $id;
    $organization->name = 'Facility Compliance Test';
    $organization->slug = $slug;
    $organization->ownerUserId = '550e8400-e29b-41d4-a716-4466554a3900';
    $organization->createdByUserId = '550e8400-e29b-41d4-a716-4466554a3900';
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-02-12T10:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;
    $this->entityManager->persist($organization);

    return $organization;
  }

  private function createFacility(
    string $id,
    OrganizationRecord $organization,
    ?FacilityRecord $parentFacility,
    string $name,
    string $type,
    string $status,
  ): FacilityRecord {
    $facility = new FacilityRecord();
    $facility->id = $id;
    $facility->organization = $organization;
    $facility->parentFacility = $parentFacility;
    $facility->type = $type;
    $facility->name = $name;
    $facility->code = null;
    $facility->status = $status;
    $facility->address = null;
    $facility->metadata = [];
    $facility->createdAt = new DateTimeImmutable('2026-02-12T10:00:00+00:00');
    $facility->updatedAt = $facility->createdAt;
    $this->entityManager->persist($facility);

    return $facility;
  }

  private function removeOrganization(string $id): void
  {
    foreach ($this->entityManager->getRepository(FacilityRecord::class)->findBy(['organization' => $id]) as $record) {
      $record->parentFacility = null;
    }
    $this->entityManager->flush();
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
