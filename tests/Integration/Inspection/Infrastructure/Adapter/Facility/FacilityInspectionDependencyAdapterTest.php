<?php

declare(strict_types=1);

namespace Tests\Integration\Inspection\Infrastructure\Adapter\Facility;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Inspection\Infrastructure\Adapter\Facility\FacilityInspectionDependencyAdapter;
use Inspection\Infrastructure\Persistence\Doctrine\Record\InspectionRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test FacilityInspectionDependencyAdapter.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(FacilityInspectionDependencyAdapter::class)]
final class FacilityInspectionDependencyAdapterTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = 'f10e8400-e29b-41d4-a716-446655f10001';

  private const string OTHER_ORGANIZATION_ID = 'f10e8400-e29b-41d4-a716-446655f10002';

  private const string FACILITY_ACTIVE = 'f10e8400-e29b-41d4-a716-446655f1a001';

  private const string FACILITY_CLOSED = 'f10e8400-e29b-41d4-a716-446655f1c001';

  private const string FACILITY_DRAFT_RECORD = 'f10e8400-e29b-41d4-a716-446655f1d001';

  private const string FACILITY_FOREIGN = 'f10e8400-e29b-41d4-a716-446655f1e001';

  private const string INSPECTION_ACTIVE = 'f10e8400-e29b-41d4-a716-446655f11001';

  private const string INSPECTION_CLOSED = 'f10e8400-e29b-41d4-a716-446655f11002';

  private const string INSPECTION_DRAFT_RECORD = 'f10e8400-e29b-41d4-a716-446655f11003';

  private const string INSPECTION_FOREIGN = 'f10e8400-e29b-41d4-a716-446655f11004';

  private const string EQUIPMENT_ID = 'f10e8400-e29b-41d4-a716-446655f18001';

  private const string OWNER_USER_ID = 'f10e8400-e29b-41d4-a716-446655f19000';

  private EntityManagerInterface $entityManager;

  private FacilityInspectionDependencyAdapter $adapter;

  protected function setUp(): void
  {
    self::bootKernel();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    $this->adapter = new FacilityInspectionDependencyAdapter($this->entityManager);

    $organization = $this->createOrganization(self::ORGANIZATION_ID, 'Facility Dependency Org', 'facility-dependency-org');
    $otherOrganization = $this->createOrganization(self::OTHER_ORGANIZATION_ID, 'Facility Dependency Org B', 'facility-dependency-org-b');
    $this->entityManager->persist($organization);
    $this->entityManager->persist($otherOrganization);

    // In-progress (submitted), published: an active dependency.
    $this->entityManager->persist($this->createInspection(
      id: self::INSPECTION_ACTIVE,
      organization: $organization,
      facilityId: self::FACILITY_ACTIVE,
      status: 'submitted',
      recordStatus: 'published',
    ));
    // Closed, published: no longer an active dependency.
    $this->entityManager->persist($this->createInspection(
      id: self::INSPECTION_CLOSED,
      organization: $organization,
      facilityId: self::FACILITY_CLOSED,
      status: 'closed',
      recordStatus: 'published',
    ));
    // Draft, but an unpublished revision: must be excluded by the record_status filter.
    $this->entityManager->persist($this->createInspection(
      id: self::INSPECTION_DRAFT_RECORD,
      organization: $organization,
      facilityId: self::FACILITY_DRAFT_RECORD,
      status: 'draft',
      recordStatus: 'draft',
    ));
    // Active inspection, but belonging to a different organization.
    $this->entityManager->persist($this->createInspection(
      id: self::INSPECTION_FOREIGN,
      organization: $otherOrganization,
      facilityId: self::FACILITY_FOREIGN,
      status: 'draft',
      recordStatus: 'published',
    ));

    $this->entityManager->flush();
  }

  protected function tearDown(): void
  {
    $this->cleanup();
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testReturnsTrueWhenFacilityHasActiveInspection(): void
  {
    self::assertTrue(
      $this->adapter->hasActiveInspectionInFacility(self::ORGANIZATION_ID, self::FACILITY_ACTIVE),
    );
  }

  #[Test]
  public function testReturnsFalseWhenOnlyClosedInspections(): void
  {
    self::assertFalse(
      $this->adapter->hasActiveInspectionInFacility(self::ORGANIZATION_ID, self::FACILITY_CLOSED),
    );
  }

  #[Test]
  public function testIgnoresUnpublishedInspectionRevisions(): void
  {
    self::assertFalse(
      $this->adapter->hasActiveInspectionInFacility(self::ORGANIZATION_ID, self::FACILITY_DRAFT_RECORD),
    );
  }

  #[Test]
  public function testIsScopedToOrganization(): void
  {
    // The active inspection in FACILITY_FOREIGN belongs to the other organization.
    self::assertFalse(
      $this->adapter->hasActiveInspectionInFacility(self::ORGANIZATION_ID, self::FACILITY_FOREIGN),
    );
    self::assertTrue(
      $this->adapter->hasActiveInspectionInFacility(self::OTHER_ORGANIZATION_ID, self::FACILITY_FOREIGN),
    );
  }

  private function createInspection(
    string $id,
    OrganizationRecord $organization,
    string $facilityId,
    string $status,
    string $recordStatus,
  ): InspectionRecord {
    $inspection = new InspectionRecord();
    $inspection->id = $id;
    $inspection->organization = $organization;
    $inspection->facilityId = $facilityId;
    $inspection->recordStatus = $recordStatus;
    $inspection->equipmentId = self::EQUIPMENT_ID;
    $inspection->inspectorType = 'user';
    $inspection->inspectorName = 'Jane Doe';
    $inspection->result = 'pass';
    $inspection->status = $status;
    $inspection->performedAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $inspection->createdAt = $inspection->performedAt;
    $inspection->updatedAt = $inspection->performedAt;

    return $inspection;
  }

  private function createOrganization(string $id, string $name, string $slug): OrganizationRecord
  {
    $organization = new OrganizationRecord();
    $organization->id = $id;
    $organization->name = $name;
    $organization->slug = $slug;
    $organization->ownerUserId = self::OWNER_USER_ID;
    $organization->createdByUserId = self::OWNER_USER_ID;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;

    return $organization;
  }

  private function cleanup(): void
  {
    $connection = $this->entityManager->getConnection();
    $inspectionIds = [
      self::INSPECTION_ACTIVE,
      self::INSPECTION_CLOSED,
      self::INSPECTION_DRAFT_RECORD,
      self::INSPECTION_FOREIGN,
    ];
    $connection->executeStatement(
      'DELETE FROM inspections WHERE id IN (:inspectionIds)',
      ['inspectionIds' => $inspectionIds],
      ['inspectionIds' => ArrayParameterType::STRING],
    );
    $connection->executeStatement(
      'DELETE FROM organizations WHERE id IN (:organizationIds)',
      ['organizationIds' => [self::ORGANIZATION_ID, self::OTHER_ORGANIZATION_ID]],
      ['organizationIds' => ArrayParameterType::STRING],
    );
    $this->entityManager->clear();
  }
}
