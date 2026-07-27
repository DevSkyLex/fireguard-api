<?php

declare(strict_types=1);

namespace Tests\Integration\Inspection\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Inspection\Domain\Model\Inspection\Inspection;
use Inspection\Domain\ValueObject\{
  InspectionChecklistId,
  InspectionEquipmentId,
  InspectionFacilityId,
  InspectionId,
  InspectionOrganizationId,
  InspectionResult,
  InspectionStatus,
  Inspector
};
use Inspection\Infrastructure\Persistence\Doctrine\Record\InspectionRecord;
use Inspection\Infrastructure\Persistence\Doctrine\Repository\InspectionRepository;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Shared\Application\Contract\Sorting\{SortDirection, Sorting};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_map;

/**
 * Test InspectionRepositoryStatisticsTest.
 *
 * Complements InspectionRepositoryTest by exercising the branches the happy-path
 * round-trip test leaves uncovered: the save() update path, remove(),
 * findPublishedById(), every list filter plus the trigram search predicate,
 * pagination and each resolveSortField() branch, and the grouping / period /
 * per-day aggregations (countByPerformedDay's PostgreSQL bucketing).
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InspectionRepository::class)]
final class InspectionRepositoryStatisticsTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = '881e8400-e29b-41d4-a716-446655481001';

  private const string OTHER_ORGANIZATION_ID = '881e8400-e29b-41d4-a716-446655481002';

  private const string INSPECTION_A_ID = '881e8400-e29b-41d4-a716-446655481101';

  private const string INSPECTION_B_ID = '881e8400-e29b-41d4-a716-446655481102';

  private const string INSPECTION_C_ID = '881e8400-e29b-41d4-a716-446655481103';

  private const string UNPUBLISHED_INSPECTION_ID = '881e8400-e29b-41d4-a716-446655481105';

  private const string REMOVABLE_INSPECTION_ID = '881e8400-e29b-41d4-a716-446655481106';

  private const string UPSERT_INSPECTION_ID = '881e8400-e29b-41d4-a716-446655481107';

  private const string UNKNOWN_INSPECTION_ID = '881e8400-e29b-41d4-a716-446655481199';

  private const string EQUIPMENT_A_ID = '881e8400-e29b-41d4-a716-446655488001';

  private const string EQUIPMENT_B_ID = '881e8400-e29b-41d4-a716-446655488002';

  private const string FACILITY_A_ID = '881e8400-e29b-41d4-a716-446655487001';

  private const string FACILITY_B_ID = '881e8400-e29b-41d4-a716-446655487002';

  private const string CHECKLIST_A_ID = '881e8400-e29b-41d4-a716-446655486001';

  private const string INSPECTOR_USER_ID = '881e8400-e29b-41d4-a716-446655489000';

  /**
   * @var list<string>
   */
  private const array INSPECTION_IDS = [
    self::INSPECTION_A_ID,
    self::INSPECTION_B_ID,
    self::INSPECTION_C_ID,
    self::UNPUBLISHED_INSPECTION_ID,
    self::REMOVABLE_INSPECTION_ID,
    self::UPSERT_INSPECTION_ID,
  ];

  private EntityManagerInterface $entityManager;

  private InspectionRepository $repository;

  protected function setUp(): void
  {
    self::bootKernel();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    /** @var InspectionRepository $repository */
    $repository = static::getContainer()->get(InspectionRepository::class);
    $this->repository = $repository;

    $this->entityManager->persist($this->createOrganization(self::ORGANIZATION_ID, 'Inspection Stats Org', 'inspection-stats-org'));
    $this->entityManager->persist($this->createOrganization(self::OTHER_ORGANIZATION_ID, 'Inspection Stats Org B', 'inspection-stats-org-b'));
    $this->entityManager->flush();
    $this->entityManager->clear();
  }

  protected function tearDown(): void
  {
    $this->cleanup();
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testSaveUpdatesAnExistingInspectionInPlace(): void
  {
    $this->saveInspection(
      id: self::UPSERT_INSPECTION_ID,
      equipmentId: self::EQUIPMENT_A_ID,
      result: InspectionResult::PASS,
      status: InspectionStatus::DRAFT,
      inspector: Inspector::forUser(self::INSPECTOR_USER_ID, 'Alice Auditor'),
      performedAt: new DateTimeImmutable('2026-01-10 00:00:00'),
      createdAt: new DateTimeImmutable('2026-01-01 00:00:00'),
    );
    $this->entityManager->clear();

    $updated = Inspection::reconstitute(
      id: InspectionId::fromString(self::UPSERT_INSPECTION_ID),
      organizationId: InspectionOrganizationId::fromString(self::ORGANIZATION_ID),
      equipmentId: InspectionEquipmentId::fromString(self::EQUIPMENT_B_ID),
      inspector: Inspector::forUser(self::INSPECTOR_USER_ID, 'Alice Auditor'),
      result: InspectionResult::FAIL,
      status: InspectionStatus::SUBMITTED,
      performedAt: new DateTimeImmutable('2026-01-10 00:00:00'),
      createdAt: new DateTimeImmutable('2026-01-01 00:00:00'),
      updatedAt: new DateTimeImmutable('2026-01-15 00:00:00'),
      facilityId: InspectionFacilityId::fromString(self::FACILITY_A_ID),
      checklistId: InspectionChecklistId::fromString(self::CHECKLIST_A_ID),
      notes: 'Updated notes',
      signature: 'updated-signature',
    );
    $this->repository->save($updated);
    $this->entityManager->clear();

    $found = $this->repository->findById(InspectionId::fromString(self::UPSERT_INSPECTION_ID));

    self::assertNotNull($found);
    self::assertSame(self::EQUIPMENT_B_ID, (string) $found->equipmentId());
    self::assertSame('fail', $found->result()->value);
    self::assertSame('submitted', $found->status()->value);
    self::assertSame('Updated notes', $found->notes());
    self::assertSame('updated-signature', $found->signature());
    self::assertNotNull($found->facilityId());
    self::assertSame(self::FACILITY_A_ID, (string) $found->facilityId());
    // The second save must update the existing row, not insert a duplicate.
    self::assertSame(1, $this->repository->countByOrganizationId(
      InspectionOrganizationId::fromString(self::ORGANIZATION_ID),
    ));
  }

  #[Test]
  public function testRemoveDeletesAPersistedInspection(): void
  {
    $this->saveInspection(
      id: self::REMOVABLE_INSPECTION_ID,
      equipmentId: self::EQUIPMENT_A_ID,
      result: InspectionResult::PASS,
      status: InspectionStatus::DRAFT,
      inspector: Inspector::forUser(self::INSPECTOR_USER_ID, 'Alice Auditor'),
      performedAt: new DateTimeImmutable('2026-01-10 00:00:00'),
      createdAt: new DateTimeImmutable('2026-01-01 00:00:00'),
    );

    $inspection = $this->repository->findById(InspectionId::fromString(self::REMOVABLE_INSPECTION_ID));
    self::assertNotNull($inspection);

    $this->repository->remove($inspection);
    $this->entityManager->clear();

    self::assertNull($this->repository->findById(InspectionId::fromString(self::REMOVABLE_INSPECTION_ID)));
  }

  #[Test]
  public function testRemoveIsANoOpForAnUnknownInspection(): void
  {
    $unsaved = Inspection::reconstitute(
      id: InspectionId::fromString(self::UNKNOWN_INSPECTION_ID),
      organizationId: InspectionOrganizationId::fromString(self::ORGANIZATION_ID),
      equipmentId: InspectionEquipmentId::fromString(self::EQUIPMENT_A_ID),
      inspector: Inspector::forUser(self::INSPECTOR_USER_ID, 'Alice Auditor'),
      result: InspectionResult::PASS,
      status: InspectionStatus::DRAFT,
      performedAt: new DateTimeImmutable('2026-01-10 00:00:00'),
      createdAt: new DateTimeImmutable('2026-01-01 00:00:00'),
      updatedAt: new DateTimeImmutable('2026-01-01 00:00:00'),
    );

    $this->repository->remove($unsaved);

    self::assertNull($this->repository->findById(InspectionId::fromString(self::UNKNOWN_INSPECTION_ID)));
  }

  #[Test]
  public function testFindPublishedByIdReturnsThePublishedInspection(): void
  {
    $this->saveInspection(
      id: self::INSPECTION_A_ID,
      equipmentId: self::EQUIPMENT_A_ID,
      result: InspectionResult::PASS,
      status: InspectionStatus::DRAFT,
      inspector: Inspector::forUser(self::INSPECTOR_USER_ID, 'Alice Auditor'),
      performedAt: new DateTimeImmutable('2026-01-10 00:00:00'),
      createdAt: new DateTimeImmutable('2026-01-01 00:00:00'),
    );
    $this->entityManager->clear();

    $found = $this->repository->findPublishedById(InspectionId::fromString(self::INSPECTION_A_ID));

    self::assertNotNull($found);
    self::assertSame(self::INSPECTION_A_ID, (string) $found->id());
  }

  #[Test]
  public function testFindPublishedByIdReturnsNullForANonPublishedRecord(): void
  {
    $this->persistUnpublishedRecord(self::UNPUBLISHED_INSPECTION_ID);

    self::assertNull(
      $this->repository->findPublishedById(InspectionId::fromString(self::UNPUBLISHED_INSPECTION_ID)),
    );
    // findById ignores the record status, so the same row is still resolvable there.
    self::assertNotNull(
      $this->repository->findById(InspectionId::fromString(self::UNPUBLISHED_INSPECTION_ID)),
    );
  }

  #[Test]
  public function testFindPublishedByIdReturnsNullForUnknownId(): void
  {
    self::assertNull(
      $this->repository->findPublishedById(InspectionId::fromString(self::UNKNOWN_INSPECTION_ID)),
    );
  }

  #[Test]
  public function testFindByOrganizationIdAppliesEveryFilter(): void
  {
    $this->seedStandardDataset();
    $organizationId = InspectionOrganizationId::fromString(self::ORGANIZATION_ID);

    self::assertEqualsCanonicalizing(
      [self::INSPECTION_A_ID, self::INSPECTION_C_ID],
      $this->ids($this->repository->findByOrganizationId($organizationId, equipmentId: self::EQUIPMENT_A_ID)),
    );
    self::assertEqualsCanonicalizing(
      [self::INSPECTION_B_ID, self::INSPECTION_C_ID],
      $this->ids($this->repository->findByOrganizationId($organizationId, facilityId: self::FACILITY_B_ID)),
    );
    self::assertSame(
      [self::INSPECTION_B_ID],
      $this->ids($this->repository->findByOrganizationId($organizationId, result: 'fail')),
    );
    self::assertSame(
      [self::INSPECTION_C_ID],
      $this->ids($this->repository->findByOrganizationId($organizationId, status: 'closed')),
    );
    self::assertEqualsCanonicalizing(
      [self::INSPECTION_A_ID, self::INSPECTION_C_ID],
      $this->ids($this->repository->findByOrganizationId($organizationId, inspectorUserId: self::INSPECTOR_USER_ID)),
    );
    self::assertSame(
      [self::INSPECTION_B_ID],
      $this->ids($this->repository->findByOrganizationId($organizationId, inspectorType: 'external')),
    );
    self::assertEqualsCanonicalizing(
      [self::INSPECTION_A_ID, self::INSPECTION_C_ID],
      $this->ids($this->repository->findByOrganizationId($organizationId, checklistId: self::CHECKLIST_A_ID)),
    );
    // Performed-at window keeps only the February inspection.
    self::assertSame(
      [self::INSPECTION_B_ID],
      $this->ids($this->repository->findByOrganizationId(
        $organizationId,
        performedAtFrom: '2026-02-01T00:00:00+00:00',
        performedAtTo: '2026-02-28T23:59:59+00:00',
      )),
    );
    // Trigram/LIKE search matches the external inspector name only.
    self::assertSame(
      [self::INSPECTION_B_ID],
      $this->ids($this->repository->findByOrganizationId($organizationId, search: 'bob')),
    );
  }

  #[Test]
  public function testFindByOrganizationIdPaginatesAndSortsByEveryField(): void
  {
    $this->seedStandardDataset();
    $organizationId = InspectionOrganizationId::fromString(self::ORGANIZATION_ID);

    // performedAt DESC: C (2026-03) then B (2026-02) then A (2026-01).
    $firstPage = $this->repository->findByOrganizationId(
      $organizationId,
      sorting: new Sorting('performedAt', SortDirection::DESC),
      limit: 2,
      offset: 0,
    );
    self::assertSame([self::INSPECTION_C_ID, self::INSPECTION_B_ID], $this->ids($firstPage));

    $secondPage = $this->repository->findByOrganizationId(
      $organizationId,
      sorting: new Sorting('performedAt', SortDirection::DESC),
      limit: 2,
      offset: 2,
    );
    self::assertSame([self::INSPECTION_A_ID], $this->ids($secondPage));

    // Unknown sort field falls back to createdAt ASC: A, B, C.
    self::assertSame(
      [self::INSPECTION_A_ID, self::INSPECTION_B_ID, self::INSPECTION_C_ID],
      $this->ids($this->repository->findByOrganizationId($organizationId, sorting: new Sorting('unknown'))),
    );
    // The remaining resolveSortField() branches must run without error.
    self::assertCount(3, $this->repository->findByOrganizationId($organizationId, sorting: new Sorting('result')));
    self::assertCount(3, $this->repository->findByOrganizationId($organizationId, sorting: new Sorting('status')));
  }

  #[Test]
  public function testCountByOrganizationIdHonoursFilters(): void
  {
    $this->seedStandardDataset();
    $organizationId = InspectionOrganizationId::fromString(self::ORGANIZATION_ID);

    self::assertSame(3, $this->repository->countByOrganizationId($organizationId));
    self::assertSame(1, $this->repository->countByOrganizationId($organizationId, result: 'fail'));
    self::assertSame(2, $this->repository->countByOrganizationId($organizationId, equipmentId: self::EQUIPMENT_A_ID));
    self::assertSame(0, $this->repository->countByOrganizationId($organizationId, status: 'draft', result: 'fail'));
  }

  #[Test]
  public function testCountByStatusResultAndInspectorTypeGroupings(): void
  {
    $this->seedStandardDataset();
    $organizationId = InspectionOrganizationId::fromString(self::ORGANIZATION_ID);

    // Key order is unspecified (no ORDER BY on the GROUP BY), so assert per key.
    $byStatus = $this->repository->countByStatusForOrganizationId($organizationId);
    self::assertCount(3, $byStatus);
    self::assertSame(1, $byStatus['draft']);
    self::assertSame(1, $byStatus['submitted']);
    self::assertSame(1, $byStatus['closed']);

    $byResult = $this->repository->countByResultForOrganizationId($organizationId);
    self::assertCount(3, $byResult);
    self::assertSame(1, $byResult['pass']);
    self::assertSame(1, $byResult['fail']);
    self::assertSame(1, $byResult['partial']);

    $byInspectorType = $this->repository->countByInspectorTypeForOrganizationId($organizationId);
    self::assertCount(2, $byInspectorType);
    self::assertSame(2, $byInspectorType['user']);
    self::assertSame(1, $byInspectorType['external']);
  }

  #[Test]
  public function testCountOverviewByOrganizationIdHonoursFilters(): void
  {
    $this->seedStandardDataset();
    $organizationId = InspectionOrganizationId::fromString(self::ORGANIZATION_ID);

    $overview = $this->repository->countOverviewByOrganizationId($organizationId);
    self::assertSame(3, $overview['total']);
    self::assertSame(1, $overview['draft']);
    self::assertSame(1, $overview['submitted']);
    self::assertSame(1, $overview['closed']);
    self::assertSame(1, $overview['pass']);
    self::assertSame(1, $overview['fail']);
    self::assertSame(1, $overview['partial']);

    $draftOnly = $this->repository->countOverviewByOrganizationId($organizationId, status: 'draft');
    self::assertSame(1, $draftOnly['total']);
    self::assertSame(1, $draftOnly['draft']);
    self::assertSame(1, $draftOnly['pass']);

    $failOnly = $this->repository->countOverviewByOrganizationId($organizationId, result: 'fail');
    self::assertSame(1, $failOnly['total']);
    self::assertSame(1, $failOnly['fail']);

    $externalOnly = $this->repository->countOverviewByOrganizationId($organizationId, inspectorType: 'external');
    self::assertSame(1, $externalOnly['total']);
  }

  #[Test]
  public function testCountByPerformedDayForOrganizationIdBucketsByDay(): void
  {
    $this->seedStandardDataset();

    $counts = $this->repository->countByPerformedDayForOrganizationId(
      InspectionOrganizationId::fromString(self::ORGANIZATION_ID),
      '2026-01-01T00:00:00+00:00',
      '2026-12-31T23:59:59+00:00',
      'UTC',
    );

    self::assertCount(3, $counts);
    self::assertSame(1, $counts['2026-01-10']);
    self::assertSame(1, $counts['2026-02-15']);
    self::assertSame(1, $counts['2026-03-20']);
  }

  #[Test]
  public function testCountPeriodMetricsByOrganizationId(): void
  {
    $this->seedStandardDataset();
    $organizationId = InspectionOrganizationId::fromString(self::ORGANIZATION_ID);

    $metrics = $this->repository->countPeriodMetricsByOrganizationId(
      $organizationId,
      '2026-01-01T00:00:00+00:00',
      '2026-12-31T23:59:59+00:00',
    );
    self::assertSame(3, $metrics['total']);
    self::assertSame(1, $metrics['closed']);
    self::assertSame(1, $metrics['pass']);
    self::assertSame(1, $metrics['fail']);
    self::assertSame(1, $metrics['partial']);

    // A narrower window plus a status filter must exercise the optional branches.
    $filtered = $this->repository->countPeriodMetricsByOrganizationId(
      $organizationId,
      '2026-02-01T00:00:00+00:00',
      '2026-03-31T23:59:59+00:00',
      status: 'closed',
    );
    self::assertSame(1, $filtered['total']);
    self::assertSame(1, $filtered['closed']);
    self::assertSame(0, $filtered['pass']);
  }

  private function seedStandardDataset(): void
  {
    $this->saveInspection(
      id: self::INSPECTION_A_ID,
      equipmentId: self::EQUIPMENT_A_ID,
      result: InspectionResult::PASS,
      status: InspectionStatus::DRAFT,
      inspector: Inspector::forUser(self::INSPECTOR_USER_ID, 'Alice Auditor'),
      performedAt: new DateTimeImmutable('2026-01-10 00:00:00'),
      createdAt: new DateTimeImmutable('2026-01-01 00:00:00'),
      facilityId: self::FACILITY_A_ID,
      checklistId: self::CHECKLIST_A_ID,
    );
    $this->saveInspection(
      id: self::INSPECTION_B_ID,
      equipmentId: self::EQUIPMENT_B_ID,
      result: InspectionResult::FAIL,
      status: InspectionStatus::SUBMITTED,
      inspector: Inspector::forExternal('Bob External', 'ControlOrg'),
      performedAt: new DateTimeImmutable('2026-02-15 00:00:00'),
      createdAt: new DateTimeImmutable('2026-01-02 00:00:00'),
      facilityId: self::FACILITY_B_ID,
    );
    $this->saveInspection(
      id: self::INSPECTION_C_ID,
      equipmentId: self::EQUIPMENT_A_ID,
      result: InspectionResult::PARTIAL,
      status: InspectionStatus::CLOSED,
      inspector: Inspector::forUser(self::INSPECTOR_USER_ID, 'Alice Auditor'),
      performedAt: new DateTimeImmutable('2026-03-20 00:00:00'),
      createdAt: new DateTimeImmutable('2026-01-03 00:00:00'),
      facilityId: self::FACILITY_B_ID,
      checklistId: self::CHECKLIST_A_ID,
    );
    $this->entityManager->clear();
  }

  private function saveInspection(
    string $id,
    string $equipmentId,
    InspectionResult $result,
    InspectionStatus $status,
    Inspector $inspector,
    DateTimeImmutable $performedAt,
    DateTimeImmutable $createdAt,
    ?string $facilityId = null,
    ?string $checklistId = null,
  ): void {
    $inspection = Inspection::reconstitute(
      id: InspectionId::fromString($id),
      organizationId: InspectionOrganizationId::fromString(self::ORGANIZATION_ID),
      equipmentId: InspectionEquipmentId::fromString($equipmentId),
      inspector: $inspector,
      result: $result,
      status: $status,
      performedAt: $performedAt,
      createdAt: $createdAt,
      updatedAt: $createdAt,
      facilityId: null !== $facilityId ? InspectionFacilityId::fromString($facilityId) : null,
      checklistId: null !== $checklistId ? InspectionChecklistId::fromString($checklistId) : null,
    );

    $this->repository->save($inspection);
  }

  private function persistUnpublishedRecord(string $id): void
  {
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, self::ORGANIZATION_ID);

    $record = new InspectionRecord();
    $record->id = $id;
    $record->organization = $organization;
    $record->recordStatus = 'draft';
    $record->equipmentId = self::EQUIPMENT_A_ID;
    $record->inspectorType = 'user';
    $record->inspectorName = 'Draft Inspector';
    $record->inspectorUserId = self::INSPECTOR_USER_ID;
    $record->result = 'pass';
    $record->status = 'draft';
    $record->performedAt = new DateTimeImmutable('2026-01-05 00:00:00');
    $record->createdAt = new DateTimeImmutable('2026-01-05 00:00:00');
    $record->updatedAt = new DateTimeImmutable('2026-01-05 00:00:00');

    $this->entityManager->persist($record);
    $this->entityManager->flush();
    $this->entityManager->clear();
  }

  /**
   * @param list<Inspection> $inspections
   *
   * @return list<string>
   */
  private function ids(array $inspections): array
  {
    return array_map(static fn (Inspection $inspection): string => (string) $inspection->id(), $inspections);
  }

  private function createOrganization(string $id, string $name, string $slug): OrganizationRecord
  {
    $organization = new OrganizationRecord();
    $organization->id = $id;
    $organization->name = $name;
    $organization->slug = $slug;
    $organization->ownerUserId = self::INSPECTOR_USER_ID;
    $organization->createdByUserId = self::INSPECTOR_USER_ID;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;

    return $organization;
  }

  private function cleanup(): void
  {
    $connection = $this->entityManager->getConnection();
    $connection->executeStatement(
      'DELETE FROM non_conformities WHERE inspection_id IN (:inspectionIds)',
      ['inspectionIds' => self::INSPECTION_IDS],
      ['inspectionIds' => ArrayParameterType::STRING],
    );
    $connection->executeStatement(
      'DELETE FROM inspections WHERE id IN (:inspectionIds)',
      ['inspectionIds' => self::INSPECTION_IDS],
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
