<?php

declare(strict_types=1);

namespace Tests\Integration\Inspection\Infrastructure\Adapter\Compliance;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Inspection\Infrastructure\Adapter\Compliance\InspectionComplianceStatisticsAdapter;
use Inspection\Infrastructure\Persistence\Doctrine\Record\{InspectionRecord, NonConformityRecord};
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test InspectionComplianceStatisticsAdapter.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InspectionComplianceStatisticsAdapter::class)]
final class InspectionComplianceStatisticsAdapterTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = 'c30e8400-e29b-41d4-a716-446655c30001';

  private const string OTHER_ORGANIZATION_ID = 'c30e8400-e29b-41d4-a716-446655c30002';

  private const string FACILITY_ONE = 'c30e8400-e29b-41d4-a716-446655c3f001';

  private const string FACILITY_TWO = 'c30e8400-e29b-41d4-a716-446655c3f002';

  private const string INSPECTION_FACILITY_ONE = 'c30e8400-e29b-41d4-a716-446655c31001';

  private const string INSPECTION_FACILITY_TWO = 'c30e8400-e29b-41d4-a716-446655c31002';

  private const string INSPECTION_UNASSIGNED = 'c30e8400-e29b-41d4-a716-446655c31003';

  private const string INSPECTION_FOREIGN = 'c30e8400-e29b-41d4-a716-446655c31004';

  private const string EQUIPMENT_ID = 'c30e8400-e29b-41d4-a716-446655c38001';

  private const string OWNER_USER_ID = 'c30e8400-e29b-41d4-a716-446655c39000';

  /**
   * @var list<string>
   */
  private const array NON_CONFORMITY_IDS = [
    'c30e8400-e29b-41d4-a716-446655c3c001',
    'c30e8400-e29b-41d4-a716-446655c3c002',
    'c30e8400-e29b-41d4-a716-446655c3c003',
    'c30e8400-e29b-41d4-a716-446655c3c004',
    'c30e8400-e29b-41d4-a716-446655c3c005',
    'c30e8400-e29b-41d4-a716-446655c3c006',
  ];

  private EntityManagerInterface $entityManager;

  private InspectionComplianceStatisticsAdapter $adapter;

  protected function setUp(): void
  {
    self::bootKernel();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    $this->adapter = new InspectionComplianceStatisticsAdapter($this->entityManager);

    $organization = $this->createOrganization(self::ORGANIZATION_ID, 'Compliance Statistics Org', 'compliance-statistics-org');
    $otherOrganization = $this->createOrganization(self::OTHER_ORGANIZATION_ID, 'Compliance Statistics Org B', 'compliance-statistics-org-b');
    $this->entityManager->persist($organization);
    $this->entityManager->persist($otherOrganization);

    $inspectionFacilityOne = $this->createInspection(self::INSPECTION_FACILITY_ONE, $organization, self::FACILITY_ONE);
    $inspectionFacilityTwo = $this->createInspection(self::INSPECTION_FACILITY_TWO, $organization, self::FACILITY_TWO);
    $inspectionUnassigned = $this->createInspection(self::INSPECTION_UNASSIGNED, $organization, null);
    $inspectionForeign = $this->createInspection(self::INSPECTION_FOREIGN, $otherOrganization, self::FACILITY_ONE);
    $this->entityManager->persist($inspectionFacilityOne);
    $this->entityManager->persist($inspectionFacilityTwo);
    $this->entityManager->persist($inspectionUnassigned);
    $this->entityManager->persist($inspectionForeign);

    // Facility one: two open high defects (one open, one in_progress) + one resolved
    // low defect that must not be counted (status not open/in_progress).
    $this->entityManager->persist($this->createNonConformity(self::NON_CONFORMITY_IDS[0], $inspectionFacilityOne, 'high', 'open'));
    $this->entityManager->persist($this->createNonConformity(self::NON_CONFORMITY_IDS[1], $inspectionFacilityOne, 'high', 'in_progress'));
    $this->entityManager->persist($this->createNonConformity(self::NON_CONFORMITY_IDS[2], $inspectionFacilityOne, 'low', 'done'));
    // Facility two: one open critical defect.
    $this->entityManager->persist($this->createNonConformity(self::NON_CONFORMITY_IDS[3], $inspectionFacilityTwo, 'critical', 'open'));
    // Unassigned facility: one open medium defect.
    $this->entityManager->persist($this->createNonConformity(self::NON_CONFORMITY_IDS[4], $inspectionUnassigned, 'medium', 'open'));
    // Foreign organization: an open high defect that must be excluded from the org scope.
    $this->entityManager->persist($this->createNonConformity(self::NON_CONFORMITY_IDS[5], $inspectionForeign, 'high', 'open'));

    $this->entityManager->flush();
  }

  protected function tearDown(): void
  {
    $this->cleanup();
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testAggregatesOpenNonConformitiesBySeverityGroupedByFacility(): void
  {
    $counts = $this->adapter->openNonConformitiesBySeverityByFacility(self::ORGANIZATION_ID);

    self::assertArrayHasKey(self::FACILITY_ONE, $counts);
    self::assertSame(2, $counts[self::FACILITY_ONE]['high']);
    // The resolved low defect is excluded by the open-status filter.
    self::assertSame(0, $counts[self::FACILITY_ONE]['low']);

    self::assertSame(1, $counts[self::FACILITY_TWO]['critical']);
    self::assertSame(1, $counts['unassigned']['medium']);
  }

  #[Test]
  public function testIsScopedToTheOrganization(): void
  {
    $counts = $this->adapter->openNonConformitiesBySeverityByFacility(self::OTHER_ORGANIZATION_ID);

    // Only the foreign organization's single open high defect in facility one.
    self::assertSame(1, $counts[self::FACILITY_ONE]['high']);
    self::assertArrayNotHasKey(self::FACILITY_TWO, $counts);
    self::assertArrayNotHasKey('unassigned', $counts);
  }

  private function createNonConformity(
    string $id,
    InspectionRecord $inspection,
    string $severity,
    string $status,
  ): NonConformityRecord {
    $nonConformity = new NonConformityRecord();
    $nonConformity->id = $id;
    $nonConformity->inspection = $inspection;
    $nonConformity->description = 'Defect ' . $id;
    $nonConformity->severity = $severity;
    $nonConformity->status = $status;
    $nonConformity->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $nonConformity->updatedAt = $nonConformity->createdAt;

    return $nonConformity;
  }

  private function createInspection(string $id, OrganizationRecord $organization, ?string $facilityId): InspectionRecord
  {
    $inspection = new InspectionRecord();
    $inspection->id = $id;
    $inspection->organization = $organization;
    $inspection->facilityId = $facilityId;
    $inspection->equipmentId = self::EQUIPMENT_ID;
    $inspection->inspectorType = 'user';
    $inspection->inspectorName = 'Jane Doe';
    $inspection->result = 'fail';
    $inspection->status = 'submitted';
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
      self::INSPECTION_FACILITY_ONE,
      self::INSPECTION_FACILITY_TWO,
      self::INSPECTION_UNASSIGNED,
      self::INSPECTION_FOREIGN,
    ];
    $connection->executeStatement(
      'DELETE FROM non_conformities WHERE inspection_id IN (:inspectionIds)',
      ['inspectionIds' => $inspectionIds],
      ['inspectionIds' => ArrayParameterType::STRING],
    );
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
